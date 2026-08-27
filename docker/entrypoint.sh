#!/usr/bin/env sh
set -e

# Base directory
cd /var/www

# Ensure required runtime storage directories exist
mkdir -p \
    storage/app/public \
    storage/app/private \
    storage/fonts \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

# Fix directory permissions for www-data
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R 775 storage bootstrap/cache || true

# Discover packages now that real env vars (e.g. PUSHER_APP_KEY) are available.
# Skipped at build time (see Dockerfile) since booting the app there would
# crash with no runtime env present yet.
echo "==> Discovering packages..."
php artisan package:discover --ansi || true

# Wait for database connection if configured for postgres/mysql
if [ -n "$DB_HOST" ] && [ "$DB_CONNECTION" != "sqlite" ]; then
    echo "==> Waiting for database (${DB_HOST}:${DB_PORT:-5432}) to become ready..."
    max_retries=30
    count=0
    until php -r "
        try {
            \$driver = getenv('DB_CONNECTION') ?: 'pgsql';
            \$host = getenv('DB_HOST') ?: '127.0.0.1';
            \$port = getenv('DB_PORT') ?: (\$driver === 'pgsql' ? 5432 : 3306);
            \$db   = getenv('DB_DATABASE') ?: 'postgres';
            \$user = getenv('DB_USERNAME') ?: 'postgres';
            \$pass = getenv('DB_PASSWORD') ?: '';
            \$dsn  = \"\$driver:host=\$host;port=\$port;dbname=\$db\";
            \$pdo  = new PDO(\$dsn, \$user, \$pass, [PDO::ATTR_TIMEOUT => 3]);
            exit(0);
        } catch (Throwable \$e) {
            exit(1);
        }
    " > /dev/null 2>&1; do
        count=$((count + 1))
        if [ $count -ge $max_retries ]; then
            echo "==> ERROR: Database is not reachable after $max_retries attempts. Continuing anyway..."
            break
        fi
        echo "    Database not ready yet (attempt $count/$max_retries)... waiting 2s"
        sleep 2
    done
    echo "==> Database connection established successfully!"
fi

# Generate APP_KEY if missing
if [ -z "$APP_KEY" ]; then
    if [ ! -f .env ] || ! grep -q "^APP_KEY=base64:" .env; then
        echo "==> Generating application key..."
        php artisan key:generate --force --no-interaction || true
    fi
fi

# Generate JWT_SECRET if missing
if [ -z "$JWT_SECRET" ]; then
    if [ ! -f .env ] || ! grep -q "^JWT_SECRET=" .env; then
        echo "==> Generating JWT secret..."
        php artisan jwt:secret --force --no-interaction || true
    fi
fi

# Run database migrations if enabled
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "==> Running database migrations..."
    php artisan migrate --force --no-interaction || echo "==> Migrations skipped or failed."
fi

# Run seeders if explicitly requested
if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    echo "==> Running database seeders..."
    php artisan db:seed --force --no-interaction || true
fi

# Run module-specific seeder if requested
if [ -n "$MODULE_SEED" ]; then
    echo "==> Seeding module: $MODULE_SEED..."
    php artisan module:seed "$MODULE_SEED" --force --no-interaction || true
fi

# Clear or cache configuration according to environment
if [ "${APP_ENV:-production}" = "production" ]; then
    echo "==> Optimizing configuration for production..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
else
    echo "==> Clearing cache for development..."
    php artisan config:clear || true
    php artisan route:clear || true
    php artisan view:clear || true
fi

# Execute main container command
echo "==> Starting process: $@"
exec "$@"
