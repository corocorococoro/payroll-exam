#!/usr/bin/env bash

set -Eeuo pipefail

export SERVER_NAME=":${PORT:-8080}"

php artisan migrate --force
php artisan content:sync --no-interaction
php artisan db:seed --class=Database\\Seeders\\GamificationSeeder --force
php artisan content:audit --strict
php artisan config:cache

php artisan queue:work --sleep=1 --tries=3 --timeout=90 &
queue_pid=$!
php artisan schedule:work &
schedule_pid=$!
"$@" &
server_pid=$!

cleanup() {
    trap - EXIT INT TERM
    kill -TERM "$queue_pid" "$schedule_pid" "$server_pid" 2>/dev/null || true
    wait "$queue_pid" "$schedule_pid" "$server_pid" 2>/dev/null || true
}

trap cleanup EXIT INT TERM

# Web、queue、schedulerのどれかが停止したらコンテナごと再起動させる。
set +e
wait -n "$queue_pid" "$schedule_pid" "$server_pid"
status=$?
set -e

if [ "$status" -eq 0 ]; then
    status=1
fi

exit "$status"
