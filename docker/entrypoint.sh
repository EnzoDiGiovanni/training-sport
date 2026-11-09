#!/bin/sh
set -e
# Le volume storage/ peut être vide au premier démarrage.
mkdir -p storage/app/public/exercises storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage

php artisan storage:link --force >/dev/null 2>&1 || true
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
