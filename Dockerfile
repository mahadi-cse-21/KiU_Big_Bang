# BUP CSE Fest 2026 Hackathon - GridWise Energy Optimizer
# Multi-stage lightweight production Dockerfile
FROM php:8.2-cli-alpine

# Install system dependencies & PHP extensions
RUN apk add --no-cache \
    curl \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install bcmath pcntl

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for layer caching
COPY composer.json composer.lock ./

# Install production dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Copy application source code
COPY . .

# Run post-autoload scripts & create storage links
RUN cp .env.example .env && \
    php artisan key:generate --force && \
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Expose service port (default: 8000)
EXPOSE 8000

# Bind to 0.0.0.0 so external judge traffic can reach the container
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
