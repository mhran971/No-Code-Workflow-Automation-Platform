FROM php:8.2-fpm

# Set environment variables
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    DEBIAN_FRONTEND=noninteractive

# Install system dependencies & build tools
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    unzip \
    zip \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libicu-dev \
    libpq-dev \
    libzip-dev \
    libonig-dev \
    procps \
    nginx \
    supervisor \
    gettext-base \
    && rm -f /etc/nginx/sites-enabled/default \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        intl \
        pdo_pgsql \
        pgsql \
        zip \
        mbstring \
        exif \
        pcntl \
        bcmath \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install Node.js LTS (for building frontend / Filament assets if needed)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /var/www

# Copy custom PHP configuration
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

# Copy nginx config template (rendered with $PORT at container start) and
# the supervisord config used to run nginx + php-fpm together as the "web" role
COPY docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy entrypoint script and set executable permissions
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Copy composer files first for layer caching
COPY composer.json composer.lock ./

# Install dependencies (ignoring scripts initially)
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist

# Copy the rest of the application codebase
COPY . .

# Generate optimized autoload files. --no-scripts skips `artisan package:discover`
# (Composer's postAutoloadDump hook), which boots the app and would otherwise
# crash here: no runtime env vars (e.g. PUSHER_APP_KEY) exist during the image
# build, only once Railway injects them into the running container. Package
# discovery instead runs from entrypoint.sh, once real env vars are present.
RUN composer dump-autoload --optimize --no-scripts \
    && chown -R www-data:www-data /var/www \
    && chmod -R 775 storage bootstrap/cache

# Expose FastCGI port
EXPOSE 9000

# Set entrypoint and default command
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
