# Stage 1: Composer deps (needed by Vite for vendor/livewire/flux CSS)
FROM composer:2 AS composer-deps
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --ignore-platform-reqs

# Stage 2: Node — build Vite assets (needs vendor/livewire/flux/dist/flux.css)
FROM node:20-alpine AS node-builder
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY . .
COPY --from=composer-deps /app/vendor ./vendor
RUN npm run build

# Stage 2: PHP-FPM + nginx (single container for Coolify)
FROM php:8.2-fpm-alpine

# System deps
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    postgresql-dev \
    sqlite-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache

WORKDIR /var/www/html

# Application source
COPY . .

# Reuse vendor from composer-deps stage (already built with --no-dev)
COPY --from=composer-deps /app/vendor ./vendor

# Vite built assets from Stage 1
COPY --from=node-builder /app/public/build ./public/build

# Storage & cache dirs
RUN mkdir -p storage/logs storage/framework/{sessions,views,cache} bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 storage bootstrap/cache

# Configs
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

CMD ["/entrypoint.sh"]
