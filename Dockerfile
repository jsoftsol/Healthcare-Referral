# Dockerfile
FROM php:8.5.3-fpm-alpine

# Install system + build deps
RUN apk add --no-cache \
    git curl zip unzip oniguruma-dev \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    libzip-dev \
    $PHPIZE_DEPS

# Configure + install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip

# Install Redis PHP extension
RUN pecl install redis \
 && docker-php-ext-enable redis

# (Optional) reduce image size a bit
RUN apk del --no-cache $PHPIZE_DEPS

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files and install dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts

# Copy application code
COPY . .

# Set permissions (Laravel)
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
 && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
