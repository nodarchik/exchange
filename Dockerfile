# Multi-stage Dockerfile for Symfony application
# Supports both development and production builds

# Base image with PHP 8.2 and necessary extensions
FROM php:8.2-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip \
    bash \
    mysql-client \
    supervisor

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files for dependency installation
COPY composer.json composer.lock ./

# Development stage
FROM base AS dev

# Install development dependencies
RUN composer install --no-scripts --no-autoloader

# Install Xdebug for development
RUN apk add --no-cache $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug

# Copy Xdebug configuration
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# Copy PHP configuration for development
COPY docker/php/php-dev.ini /usr/local/etc/php/conf.d/99-app.ini

# Copy application code
COPY . .

# Generate autoloader and optimize for development
RUN composer dump-autoload

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/var

EXPOSE 9000

CMD ["php-fpm"]

# Production stage
FROM base AS prod

# Install production dependencies only
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy PHP configuration for production
COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/99-app.ini

# Copy application code
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --classmap-authoritative

# Create necessary directories and set permissions
RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/var

# Remove development files
RUN rm -rf tests docker .git .gitignore

# Copy supervisor configuration for background tasks
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 9000

# Use supervisor to run both PHP-FPM and scheduler
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# Cron stage for scheduled tasks (production)
FROM prod AS scheduler

# Install cron
RUN apk add --no-cache dcron

# Copy crontab
COPY docker/cron/crontab /var/spool/cron/crontabs/www-data

# Set permissions for crontab
RUN chown www-data:www-data /var/spool/cron/crontabs/www-data \
    && chmod 600 /var/spool/cron/crontabs/www-data

CMD ["crond", "-f", "-d", "8"]
