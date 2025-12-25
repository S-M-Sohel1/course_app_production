#!/bin/bash
set -e

echo "Starting Laravel application..."

# Clear any cached config from build time
php artisan config:clear || true
php artisan cache:clear || true

# Parse DATABASE_URL if provided by Render
if [ -n "$DATABASE_URL" ]; then
    echo "Parsing DATABASE_URL..."
    # Extract components from DATABASE_URL
    # Format: postgres://user:password@host:port/database or mysql://user:password@host:port/database
    
    # Determine database type from protocol
    if [[ "$DATABASE_URL" == postgres://* ]] || [[ "$DATABASE_URL" == postgresql://* ]]; then
        export DB_CONNECTION=pgsql
    else
        export DB_CONNECTION=mysql
    fi
    
    # Remove protocol
    url="${DATABASE_URL#*://}"
    
    # Extract user:password
    userpass="${url%%@*}"
    DB_USERNAME="${userpass%%:*}"
    DB_PASSWORD="${userpass#*:}"
    
    # Extract host:port/database
    hostdb="${url#*@}"
    
    # Extract database name (everything after last /)
    DB_DATABASE="${hostdb##*/}"
    # Remove query params if any
    DB_DATABASE="${DB_DATABASE%%\?*}"
    
    # Extract host:port (everything before /)
    hostport="${hostdb%%/*}"
    
    # Extract port (everything after last :)
    DB_PORT="${hostport##*:}"
    # If no port specified, use defaults
    if [ "$DB_PORT" = "$hostport" ]; then
        if [ "$DB_CONNECTION" = "pgsql" ]; then
            DB_PORT=5432
        else
            DB_PORT=3306
        fi
        DB_HOST="$hostport"
    else
        # Extract host (everything before last :)
        DB_HOST="${hostport%:*}"
    fi
    
    export DB_HOST
    export DB_PORT
    export DB_DATABASE
    export DB_USERNAME
    export DB_PASSWORD
    
    echo "Database configured: $DB_CONNECTION at $DB_HOST:$DB_PORT/$DB_DATABASE"
fi

# Wait for database to be ready (if DB_HOST is set)
if [ -n "$DB_HOST" ]; then
    echo "Waiting for database at $DB_HOST:$DB_PORT..."
    max_attempts=30
    attempt=0
    until php artisan migrate --force --no-interaction 2>/dev/null || [ $attempt -eq $max_attempts ]; do
        attempt=$((attempt + 1))
        echo "Database not ready, attempt $attempt/$max_attempts..."
        sleep 2
    done
    
    if [ $attempt -eq $max_attempts ]; then
        echo "WARNING: Could not connect to database after $max_attempts attempts"
        echo "Continuing without database..."
    else
        echo "Database connection established, running migrations..."
        php artisan migrate --force --no-interaction
        
        # Run admin user seeder
        echo "Creating admin user if not exists..."
        php artisan db:seed --class=AdminUserSeeder --force --no-interaction
    fi
else
    echo "WARNING: No database configuration found (DB_HOST or DATABASE_URL)"
    echo "Application will run without database connection"
fi

# Clear all caches to ensure fresh start
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Cache config with runtime environment variables
php artisan config:cache

# Create storage link if it doesn't exist
php artisan storage:link || true

# Set permissions
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

echo "Starting services..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
