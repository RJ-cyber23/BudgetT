#!/bin/sh
set -eu

# Render assigns the public port through $PORT. Apache's image defaults to 80.
PORT="${PORT:-80}"
sed -ri "s/^Listen 80$/Listen 0.0.0.0:${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

if [ -z "${APP_SECRET:-}" ]; then
    echo "APP_SECRET is not set. Add it to the Render environment variables." >&2
    exit 1
fi

if [ -z "${DATABASE_URL:-}" ]; then
    echo "DATABASE_URL is not set. Add the MySQL connection string to the Render environment variables." >&2
    exit 1
fi

if [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
    echo "Running Doctrine migrations..."
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
fi

echo "Warming Symfony cache..."
php bin/console cache:warmup --no-debug

# The cache was generated as root; Apache's PHP worker runs as www-data.
chown -R www-data:www-data var public/assets

exec "$@"
