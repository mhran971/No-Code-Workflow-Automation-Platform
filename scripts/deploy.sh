#!/bin/bash
set -e

#APP_PATH="$SERV00_PATH_PRODUCTION"   # يُحدَّد من المتغيرات أو اكتبه مباشرة
# مثال: APP_PATH="/home/USERNAME/domains/yourdomain.com/public_html"
APP_PATH="/home/hudashakir/domains/workflow-api-dev.hudashakir.serv00.net/public_html"
cd $APP_PATH

echo "📦 Installing dependencies..."
/usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction

echo "🔧 Running migrations..."
php artisan migrate --force

echo "🗑️ Clearing caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "🔗 Setting storage link..."
php artisan storage:link || true

echo "🔐 Setting permissions..."
chmod -R 775 storage bootstrap/cache

echo "✅ Deploy finished successfully!"
