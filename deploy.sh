#!/bin/sh
# Déploiement de Kelasi.
#
# Le script est fait pour être relancé sans crainte : chaque étape est soit
# idempotente, soit sans effet quand elle a déjà été faite.
set -e

cd "$(dirname "$0")"

if [ ! -f .env ]; then
    echo "Aucun fichier .env. Copiez .env.example, renseignez-le, puis relancez."
    exit 1
fi

# Une clé d'application absente rend les sessions et les mots de passe
# illisibles au redémarrage : mieux vaut refuser que démarrer à moitié.
if ! grep -q '^APP_KEY=base64:' .env; then
    echo "APP_KEY manquante. Générez-la : docker compose run --rm app php artisan key:generate"
    exit 1
fi

if ! grep -q '^DB_PASSWORD=.\+' .env; then
    echo "DB_PASSWORD manquant dans .env."
    exit 1
fi

# Un domaine déclaré veut dire HTTPS : Caddy se place devant nginx et obtient
# le certificat. Ce n'est pas un luxe -- hors de « localhost », un navigateur
# refuse d'enregistrer un service worker sur une origine en clair, et aucun
# téléphone ne proposera d'installer Kelasi.
DOMAINE="$(sed -n 's/^KELASI_DOMAINE=//p' .env | tail -1)"
PROFILS=""

if [ -n "$DOMAINE" ] && [ "$DOMAINE" != "localhost" ]; then
    PROFILS="--profile https"
    echo "→ Domaine déclaré : $DOMAINE — le service HTTPS sera démarré"
fi

echo "→ Construction des images"
docker compose $PROFILS build

echo "→ Démarrage des services"
docker compose $PROFILS up -d

echo "→ Attente de la base"
until docker compose exec -T db pg_isready -q; do
    sleep 2
done

echo "→ Migrations"
docker compose exec -T app php artisan migrate --force

echo "→ Mise en cache de la configuration"
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache

echo "→ Vérification"
docker compose ps

echo
if [ -n "$PROFILS" ]; then
    echo "Kelasi est en ligne sur https://${DOMAINE}"
    echo "Le certificat peut demander une minute au premier démarrage : docker compose logs caddy"
else
    echo "Kelasi est en ligne sur http://localhost:${KELASI_HTTP_PORT:-8093}"
    echo "Sans domaine ni HTTPS, l'application ne s'installera sur aucun téléphone."
    echo "Voir mobile/README.md."
fi
echo "Base de données sur le port ${KELASI_DB_PORT:-5434} (Adminer : ${KELASI_ADMINER_PORT:-8094})."
