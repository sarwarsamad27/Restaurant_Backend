#!/bin/sh
set -e

# Render tells the container which port to listen on
PORT="${PORT:-10000}"
sed -ri "s/Listen [0-9]+/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

php artisan package:discover --ansi
php artisan storage:link || true
php artisan config:cache

# Create/upgrade tables, then seed demo data only if the database is empty
php artisan migrate --force
php artisan db:seed --force

# Artisan ran as root; give Apache back ownership of writable folders
chown -R www-data:www-data storage bootstrap/cache

exec apache2-foreground
