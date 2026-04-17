FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git curl unzip zip \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libicu-dev \
    libpq-dev \
    libzip-dev \
    libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    gd intl pdo_pgsql pgsql zip mbstring exif pcntl bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . .

RUN chmod +x /var/www/docker/entrypoint.sh

RUN composer install --no-interaction --no-dev --optimize-autoloader \
    && chmod -R 775 storage bootstrap/cache

ENTRYPOINT ["/var/www/docker/entrypoint.sh"]
EXPOSE 9000
CMD ["php-fpm", "-F"]
