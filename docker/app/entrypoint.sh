#!/bin/sh
set -eu
php artisan fleet:check-schema
if [ "${APP_ENV:-}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi
exec "$@"
