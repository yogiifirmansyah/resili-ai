#!/bin/sh
set -e

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -qE '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
fi

composer install --no-interaction --prefer-dist

php artisan migrate --force --no-interaction

exec "$@"
