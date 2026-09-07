#!/bin/sh
set -e

# Le public/ servi est un volume partagé avec nginx. On le remplit depuis le
# modèle figé dans l'image : sans cela, les fichiers construits par Vite
# resteraient ceux du tout premier démarrage, et « Vite manifest not found »
# reviendrait après chaque reconstruction.
if [ -d /var/www/public-modele ]; then
    rm -rf /var/www/public/build
    cp -R /var/www/public-modele/. /var/www/public/
fi

mkdir -p \
    /var/www/storage/logs \
    /var/www/storage/framework/sessions \
    /var/www/storage/framework/views \
    /var/www/storage/framework/cache \
    /var/www/storage/app/private/documents \
    /var/www/public/vendor/livewire

chmod -R 777 /var/www/storage /var/www/bootstrap/cache 2>/dev/null || true

if [ -f /var/www/artisan ]; then
    # Les assets Livewire sont servis par nginx depuis public/ : ils doivent
    # y être publiés à chaque démarrage, la version du paquet pouvant changer.
    php /var/www/artisan livewire:publish --assets 2>/dev/null || true
    php /var/www/artisan storage:link --force 2>/dev/null || true
fi

exec "$@"
