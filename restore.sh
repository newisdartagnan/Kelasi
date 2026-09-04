#!/bin/sh
# Restauration d'une sauvegarde.
#
# Une restauration écrase les données en place : le script demande
# confirmation plutôt que de faire confiance à la ligne de commande.
set -e

cd "$(dirname "$0")"

DUMP="$1"

if [ -z "$DUMP" ] || [ ! -f "$DUMP" ]; then
    echo "Usage : ./restore.sh backups/kelasi_AAAAMMJJ_HHMMSS.dump"
    echo
    echo "Sauvegardes disponibles :"
    ls -1t backups/kelasi_*.dump 2>/dev/null | head -10 || echo "  (aucune)"
    exit 1
fi

echo "Cette opération remplace toutes les données actuelles par celles de :"
echo "  $DUMP"
printf 'Taper « oui » pour confirmer : '
read -r REPONSE

[ "$REPONSE" = "oui" ] || { echo "Annulé."; exit 1; }

echo "→ Restauration"
docker compose exec -T db pg_restore \
    -U "${KELASI_DB_USER:-kelasi_user}" \
    -d "${KELASI_DB_NAME:-kelasi}" \
    --clean --if-exists --no-owner \
    < "$DUMP"

echo "→ Migrations éventuelles"
docker compose exec -T app php artisan migrate --force

echo "Restauration terminée."
