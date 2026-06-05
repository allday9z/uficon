#!/bin/sh
set -e

# Create storage dirs FIRST — realpath() in config/view.php returns false for
# non-existent dirs, causing config:cache to store empty view.compiled path
mkdir -p storage/logs \
         storage/framework/sessions \
         storage/framework/views \
         storage/framework/cache \
         bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Clear any stale cached config before re-caching (prevents false realpath issue)
php artisan config:clear 2>/dev/null || true

# Run DB migrations
php artisan migrate --force
php artisan db:seed --class=UserSeeder --force

# Cache for production (config must run after mkdir so realpath resolves correctly)
php artisan config:cache
php artisan route:cache
# view:cache intentionally skipped — Laravel compiles on first request
# Running view:cache here causes "invalid cache path" if realpath() cached as false

# Start supervisor (nginx + php-fpm)
exec /usr/bin/supervisord -c /etc/supervisord.conf
