#!/bin/sh
set -eu
php artisan fleet:check-schema
exec "$@"
