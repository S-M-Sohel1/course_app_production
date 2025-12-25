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

# Stage 2 - Backend (Laravel + PHP + Composer)
# This stage sets up the PHP environment and installs your Laravel application.
FROM php:8.2-fpm AS backend

# Install system dependencies required by Laravel and other packages
# - git, curl, unzip, zip: Common utilities
# - libpq-dev: For PostgreSQL (if you use it)
# - libonig-dev, libzip-dev: For PHP extensions
# - libexif-dev, libgd-dev: For image manipulation
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    libpq-dev \
    libonig-dev \
    libzip-dev \
    libexif-dev \
    zip \
    && docker-php-ext-install pdo pdo_mysql mbstring zip exif bcmath

# Install Composer (PHP package manager)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set the working directory for the backend
WORKDIR /var/www

# Copy the application files from the current directory to the container
COPY . .

# Copy the built frontend assets from the 'frontend' stage
# Laravel's vite plugin outputs to 'public/build' by default
COPY --from=frontend /app/public/build ./public/build

# Install PHP dependencies with Composer
# --no-dev: Skips development dependencies
# --optimize-autoloader: Creates a more efficient autoloader
RUN composer install --no-dev --optimize-autoloader

# Set up Laravel for production
# These commands cache configuration, routes, and views for better performance
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

# Expose port 9000 and start php-fpm server
# This is the default port for PHP-FPM
EXPOSE 9000
CMD ["php-fpm"]

# --- Notes for Render Deployment ---
# 1. This Dockerfile is for your web service on Render.
# 2. For your queue workers, you can use the same image but change the startup command in your Render service to: `php artisan queue:work`
# 3. For Laravel Reverb (your WebSocket server), you'll need another service on Render. Use the same image and set the startup command to: `php artisan reverb:start --host=0.0.0.0 --port=8080`
#    You will also need to configure the port in `config/reverb.php` to use the `REVERB_PORT` environment variable.
