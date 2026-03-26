# Step 1: Base Image (official PHP 8.4-fpm based on Debian)
FROM php:8.4.16-fpm

# Step 2: System Dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    git \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev

# Step 3: Install PHP Extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Step 4: Install MongoDB extension using PECL
RUN pecl install mongodb && docker-php-ext-enable mongodb

# Step 5: Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Step 6: App Directory Setup
WORKDIR /var/www/reportmycity

# Step 7: Final Permissions
RUN chown -R www-data:www-data /var/www/reportmycity

# Expose PHP-FPM port
EXPOSE 9000

CMD ["php-fpm"]
