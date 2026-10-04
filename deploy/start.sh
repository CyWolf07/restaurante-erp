#!/bin/sh
set -eu
: "${APP_KEY:?Configura APP_KEY en las variables privadas de Render.}"
: "${PORT:=10000}"
case "$PORT" in ''|*[!0-9]*) echo 'PORT debe ser numérico.' >&2; exit 1;; esac
printf 'Listen %s\n' "$PORT" > /etc/apache2/ports.conf
sed -i "s/__PORT__/$PORT/g" /etc/apache2/sites-available/000-default.conf
mkdir -p storage/app/public storage/app/private storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache
php artisan config:cache
php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
exec apache2-foreground
