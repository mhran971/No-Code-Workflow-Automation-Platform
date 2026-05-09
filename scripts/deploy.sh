#!/bin/bash
set -e

# APP_PATH="/home/USERNAME/domains/yourdomain.com/public_html"   # يُحدَّد من المتغيرات أو اكتبه مباشرة
APP_PATH="/home/hudashakir/domains/workflow-api.hudashakir.serv00.net/public_html"   # يُحدَّد من المتغيرات أو اكتبه مباشرة
            
cd "$APP_PATH"

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
