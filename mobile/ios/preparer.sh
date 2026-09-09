#!/bin/sh
# Prépare l'enveloppe iOS. À lancer sur un Mac, Xcode installé.
#
#   ./preparer.sh kelasi.unikin.ac.cd
#
# Lire mobile/README.md avant : sur iPhone, cette enveloppe fait PERDRE les
# notifications poussées, que l'installation depuis Safari, elle, conserve.

set -eu

DOMAINE="${1:-}"

if [ -z "$DOMAINE" ]; then
    echo "Usage : ./preparer.sh <domaine>            (ex. kelasi.unikin.ac.cd)" >&2
    echo "Le domaine doit être servi en HTTPS." >&2
    exit 1
fi

case "$DOMAINE" in
    http://*|https://*)
        echo "Donner le domaine seul, sans http:// ni https://." >&2
        exit 1
        ;;
esac

DOSSIER="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
cd "$DOSSIER"

sed "s|DOMAINE|${DOMAINE}|g" capacitor.config.modele.json > capacitor.config.json

# Capacitor veut un dossier web, même vide : ici tout vient du serveur.
mkdir -p contenu-local
printf '<!doctype html><meta charset="utf-8"><title>Kelasi</title>\n' > contenu-local/index.html

npm install

if [ -d ios ]; then
    npx cap sync ios
else
    npx cap add ios
fi

echo
echo "Projet Xcode prêt. Ouvrir avec :"
echo
echo "  npx cap open ios"
echo
echo "Dans Xcode : choisir l'équipe de signature, régler le numéro de version,"
echo "puis Product > Archive pour envoyer vers App Store Connect."
