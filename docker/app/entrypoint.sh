#!/bin/sh
set -eu
if [ "${APP_ENV:-}" = "production" ]; then
    php artisan config:cache --no-ansi
fi
php artisan fleet:check-schema --no-ansi
exec "$@"
