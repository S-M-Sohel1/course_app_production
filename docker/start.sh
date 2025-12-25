#!/bin/bash
set -e

echo "Starting Laravel application..."

# Clear any cached config from build time
php artisan config:clear || true
php artisan cache:clear || true

# Wait for database to be ready (if DATABASE_HOST is set)
if [ -n "$DATABASE_HOST" ]; then
    echo "Waiting for database..."
    max_attempts=30
    attempt=0
    until php artisan migrate --force --no-interaction 2>/dev/null || [ $attempt -eq $max_attempts ]; do
        attempt=$((attempt + 1))
        echo "Database not ready, attempt $attempt/$max_attempts..."
        sleep 2
    done
    
    if [ $attempt -eq $max_attempts ]; then
        echo "WARNING: Could not connect to database after $max_attempts attempts"
    else
        echo "Database connection established, running migrations..."
        php artisan migrate --force --no-interaction
    fi
fi

# Cache config with runtime environment variables
php artisan config:cache

# Create storage link if it doesn't exist
php artisan storage:link || true

# Set permissions
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

echo "Starting services..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
