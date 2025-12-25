# Stage 1 - Build Frontend (Vite)
# This stage builds your JavaScript and CSS assets using Node.js and Vite.
FROM node:18 AS frontend

# Set the working directory
WORKDIR /app

# Copy package.json and package-lock.json (if available)
COPY package*.json ./

# Install npm dependencies
RUN npm install

# Copy the rest of your application code
COPY . .

# Build the frontend assets for production
# The output will be in /app/public/build
RUN npm run build

# Stage 2 - Backend (Laravel + PHP + Nginx)
# This stage sets up the PHP environment with Nginx web server
FROM php:8.2-fpm AS backend

# Install system dependencies and Nginx
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    libpq-dev \
    libonig-dev \
    libzip-dev \
    libexif-dev \
    libicu-dev \
    nginx \
    supervisor \
    zip \
    && docker-php-ext-install pdo pdo_mysql mbstring zip exif bcmath intl \
    && docker-php-ext-configure intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer (PHP package manager)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set the working directory for the backend
WORKDIR /var/www

# Copy the application files from the current directory to the container
COPY . .

# Copy the built frontend assets from the 'frontend' stage
COPY --from=frontend /app/public/build ./public/build

# Install PHP dependencies with Composer
RUN composer install --no-dev --optimize-autoloader

# Set up Laravel for production
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

# Set proper permissions
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Copy Nginx configuration
COPY docker/nginx/default.conf /etc/nginx/sites-available/default

# Copy supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Expose port 10000 for Render
EXPOSE 10000

# Start supervisor to manage Nginx and PHP-FPM
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# --- Notes for Render Deployment ---
# 1. This Dockerfile is for your web service on Render.
# 2. For your queue workers, you can use the same image but change the startup command in your Render service to: `php artisan queue:work`
# 3. For Laravel Reverb (your WebSocket server), you'll need another service on Render. Use the same image and set the startup command to: `php artisan reverb:start --host=0.0.0.0 --port=8080`
#    You will also need to configure the port in `config/reverb.php` to use the `REVERB_PORT` environment variable.
