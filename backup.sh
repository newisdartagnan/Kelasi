#!/bin/sh
# Sauvegarde de la base et des supports de cours.
#
# À brancher sur une tâche planifiée de l'hôte. Le format « custom » de
# pg_dump se restaure sélectivement, table par table au besoin.
set -e

cd "$(dirname "$0")"

HORODATAGE=$(date +%Y%m%d_%H%M%S)
DOSSIER="backups"
RETENTION_JOURS=${KELASI_RETENTION_JOURS:-30}

mkdir -p "$DOSSIER"

echo "→ Sauvegarde de la base"
docker compose exec -T db pg_dump \
    -U "${KELASI_DB_USER:-kelasi_user}" \
    -d "${KELASI_DB_NAME:-kelasi}" \
    --format=custom \
    > "$DOSSIER/kelasi_${HORODATAGE}.dump"

echo "→ Sauvegarde des supports déposés"
tar czf "$DOSSIER/documents_${HORODATAGE}.tar.gz" -C storage/app private 2>/dev/null || true

# Une sauvegarde vide passerait inaperçue et donnerait une fausse sécurité.
TAILLE=$(wc -c < "$DOSSIER/kelasi_${HORODATAGE}.dump")
if [ "$TAILLE" -lt 1024 ]; then
    echo "La sauvegarde fait moins d'un kilo-octet : quelque chose a échoué."
    exit 1
fi

echo "→ Purge au-delà de ${RETENTION_JOURS} jours"
find "$DOSSIER" -name 'kelasi_*.dump' -mtime "+${RETENTION_JOURS}" -delete
find "$DOSSIER" -name 'documents_*.tar.gz' -mtime "+${RETENTION_JOURS}" -delete

echo "Sauvegarde faite : $DOSSIER/kelasi_${HORODATAGE}.dump ($(( TAILLE / 1024 )) Ko)"
