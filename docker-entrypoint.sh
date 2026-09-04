#!/bin/sh
set -e

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
    php /var/www/artisan livewire:publish --assets --force 2>/dev/null || true
    php /var/www/artisan storage:link 2>/dev/null || true
fi

exec "$@"
