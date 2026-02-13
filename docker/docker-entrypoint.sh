#!/bin/sh
set -e

# ===============================================
# Docker Entrypoint Script for Laravel Marketing Mail
# ===============================================

# ===============================================
# Handle K8s ConfigMap/Secret deployments
# ===============================================
if [ -n "$APP_KEY" ]; then
    echo "Using APP_KEY from environment (K8s Secret detected)"

    if [ -f .env.example ]; then
        grep -v '^APP_KEY=' .env.example > .env 2>/dev/null || cp .env.example .env
    fi
fi

# ===============================================
# Ensure storage and cache directories exist
# ===============================================
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache

# Fix permissions for writable directories
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true

# ===============================================
# Wait for database connection (external DB)
# ===============================================
if [ -n "$DB_HOST" ] && [ -n "$DB_PORT" ]; then
    echo "Waiting for database at ${DB_HOST}:${DB_PORT}..."

    max_attempts=30
    attempt=0

    while [ $attempt -lt $max_attempts ]; do
        if nc -z "$DB_HOST" "$DB_PORT" 2>/dev/null; then
            echo "Database is ready!"
            break
        fi

        attempt=$((attempt + 1))
        echo "Waiting for database... (attempt $attempt/$max_attempts)"
        sleep 2
    done

    if [ $attempt -eq $max_attempts ]; then
        echo "Warning: Could not connect to database after $max_attempts attempts"
    fi
fi

# ===============================================
# Run migrations (optional - controlled by env)
# ===============================================
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force
fi

# ===============================================
# Cache optimization
# ===============================================
if [ "${APP_ENV:-production}" = "production" ]; then
    echo "Optimizing application cache..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

# ===============================================
# Start Supervisord
# ===============================================
echo "Starting Laravel Marketing Mail..."
echo "  - PHP-FPM on unix socket"
echo "  - Nginx on port 80"
echo "  - Cron (Laravel Scheduler runs every minute)"
echo "  - Laravel Queue Worker"

exec "$@"
