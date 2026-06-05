#!/bin/sh
set -e

# Ensure storage dirs exist with correct ownership
mkdir -p storage/logs storage/framework/{sessions,views,cache} bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Run DB migrations (--force required in non-local env)
php artisan migrate --force

# Cache config, routes, views for production performance
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start supervisor (nginx + php-fpm)
exec /usr/bin/supervisord -c /etc/supervisord.conf
