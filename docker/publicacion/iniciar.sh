#!/bin/sh
set -eu
: "${APP_KEY:?Falta APP_KEY}"
: "${JWT_SECRET:?Falta JWT_SECRET}"
: "${DB_URI:?Falta DB_URI}"
if [ "${DB_DATABASE:-}" != 'libreria_demo' ]; then
    echo 'La imagen academica requiere DB_DATABASE=libreria_demo.' >&2
    exit 1
fi
export APP_URL="${RENDER_EXTERNAL_URL:-${APP_URL:-}}"
: "${APP_URL:?Falta APP_URL o RENDER_EXTERNAL_URL}"
export FRONTEND_ORIGINS="${FRONTEND_ORIGINS:-$APP_URL}"
export PORT="${PORT:-10000}"
case "$PORT" in ''|*[!0-9]*) echo 'Puerto no valido.' >&2; exit 1;; esac
if [ "$PORT" -lt 1024 ] || [ "$PORT" -gt 65535 ]; then exit 1; fi
printf 'Listen %s\n' "$PORT" > /etc/apache2/ports.conf
sed -i "s/@PORT@/$PORT/g" /etc/apache2/sites-available/000-default.conf
php artisan package:discover --quiet
php artisan config:cache --quiet
exec apache2-foreground
