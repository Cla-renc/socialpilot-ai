#!/usr/bin/env sh
set -e

# 1. Adapt Nginx port to host environment (e.g. Render injects PORT=10000)
# Use perl-compatible in-place replacement to avoid POSIX sh bracket-escaping
# issues with sed's character classes across different /bin/sh implementations.
PORT="${PORT:-80}"
if [ -f /etc/nginx/conf.d/default.conf ]; then
    # Replace every listen directive that references port 80 with $PORT.
    # Using plain string replacement (no regex special chars) for reliability.
    sed -i "s|listen 80;|listen ${PORT};|g" /etc/nginx/conf.d/default.conf
    sed -i "s|listen \[::\]:80;|listen [::]:${PORT};|g" /etc/nginx/conf.d/default.conf
fi

# 2. Ensure runtime storage directories exist with correct permissions
mkdir -p /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/logs \
         /var/www/html/storage/app/public \
         /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# 3. Create public storage symlink if missing
if [ ! -L /var/www/html/public/storage ]; then
    php artisan storage:link --no-interaction || true
fi

# 4. Optional cache warming if database is ready
if [ "${APP_ENV:-production}" = "production" ] && [ "${SKIP_CACHE_WARMUP:-false}" != "true" ]; then
    php artisan config:clear || true
    php artisan route:clear || true
    php artisan view:clear || true
fi

# 5. Execute primary process manager (supervisord)
exec "$@"
