#!/usr/bin/env sh
set -e

cd /var/www

if [ ! -f .env ]; then
  cp .env.example .env
fi

# Ensure runtime-writable paths are owned by php-fpm worker user.
mkdir -p storage/logs storage/framework/{cache,sessions,views} bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwX storage bootstrap/cache || true

# Update .env keys from runtime environment variables when provided.
set_env() {
  key="$1"
  value="$2"
  if [ -n "$value" ]; then
    if grep -q "^${key}=" .env; then
      sed -i "s|^${key}=.*|${key}=${value}|" .env
    else
      echo "${key}=${value}" >> .env
    fi
  fi
}

set_env DB_CONNECTION "${DB_CONNECTION}"
set_env DB_HOST "${DB_HOST}"
set_env DB_PORT "${DB_PORT}"
set_env DB_DATABASE "${DB_DATABASE}"
set_env DB_USERNAME "${DB_USERNAME}"
set_env DB_PASSWORD "${DB_PASSWORD}"
set_env DB_SSLMODE "${DB_SSLMODE}"
set_env DB_URL "${DB_URL}"
set_env DATABASE_URL "${DATABASE_URL}"
set_env APP_KEY "${APP_KEY}"
set_env APP_ENV "${APP_ENV}"
set_env APP_DEBUG "${APP_DEBUG}"
set_env APP_URL "${APP_URL}"
set_env JWT_SECRET "${JWT_SECRET}"


if [ "${DB_CONNECTION}" = "sqlite" ]; then
  sqlite_path="${DB_DATABASE:-/var/www/storage/app/database.sqlite}"
  mkdir -p "$(dirname "${sqlite_path}")"
  touch "${sqlite_path}"
  set_env DB_DATABASE "${sqlite_path}"
fi

php artisan migrate --force || true

# seed the knowledge base module
php artisan module:seed KnowledgeBase

php artisan config:clear || true
if ! grep -q "^APP_KEY=base64:" .env && [ -z "${APP_KEY}" ]; then
  php artisan key:generate --force --no-interaction || true
fi

# php artisan optimize:clear || true


exec "$@"
