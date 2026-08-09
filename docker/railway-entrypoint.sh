#!/bin/sh

set -eu

export SERVER_NAME=":${PORT:-8080}"

php artisan migrate --force
php artisan db:seed --force
php artisan config:cache

php artisan queue:work --sleep=1 --tries=3 --timeout=90 &
php artisan schedule:work &

exec "$@"
