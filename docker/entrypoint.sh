#!/usr/bin/env sh
set -e

cd /var/www

if [ ! -f .env ]; then
  cp .env.example .env
fi

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
set_env APP_ENV "${APP_ENV}"
set_env APP_DEBUG "${APP_DEBUG}"
set_env APP_URL "${APP_URL}"

php artisan config:clear || true
if ! grep -q "^APP_KEY=base64:" .env; then
  php artisan key:generate --force --no-interaction || true
fi
php artisan storage:link || true

chown -R www-data:www-data storage bootstrap/cache || true

# if [ "${RUN_MIGRATIONS}" = "true" ]; then
#   php artisan migrate --force --no-interaction || true
# fi

php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true


exec "$@"
