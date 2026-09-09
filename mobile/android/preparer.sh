#!/bin/sh
# Prépare le paquet Android à partir du domaine de l'université.
#
# Bubblewrap sait interroger un site et poser lui-même les questions, mais
# les réponses -- identifiant du paquet, couleurs, raccourcis, notifications --
# sont toujours les mêmes et se retapent mal. Ce script les inscrit une fois
# pour toutes dans twa-manifest.json ; il reste à signer et à construire.
#
#   ./preparer.sh kelasi.unikin.ac.cd

set -eu

DOMAINE="${1:-}"

if [ -z "$DOMAINE" ]; then
    echo "Usage : ./preparer.sh <domaine>            (ex. kelasi.unikin.ac.cd)" >&2
    echo "Le domaine doit être servi en HTTPS et répondre sur /manifest.json." >&2
    exit 1
fi

case "$DOMAINE" in
    http://*|https://*)
        echo "Donner le domaine seul, sans http:// ni https://." >&2
        exit 1
        ;;
esac

DOSSIER="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"

sed "s|DOMAINE|${DOMAINE}|g" "${DOSSIER}/twa-manifest.modele.json" > "${DOSSIER}/twa-manifest.json"

echo "twa-manifest.json écrit pour ${DOMAINE}."
echo
echo "Ensuite, dans ce dossier :"
echo
echo "  npx @bubblewrap/cli@latest build        # signe et construit l'application"
echo "  npx @bubblewrap/cli@latest fingerprint list"
echo
echo "Reporter l'empreinte SHA-256 affichée dans le .env du serveur :"
echo
echo "  ANDROID_PAQUET=cd.kelasi.app"
echo "  ANDROID_EMPREINTES_SHA256=<empreinte>"
echo
echo "puis redémarrer l'application. Sans cela, Android affiche une barre"
echo "d'adresse par-dessus Kelasi."
