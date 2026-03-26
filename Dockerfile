# Production-Ready Standalone Dockerfile for ReportMyCity
FROM php:8.4-apache

# 1. System Dependencies
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

# 2. Apache Rewrite Module
RUN a2enmod rewrite

# 3. Base PHP Extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# 4. MongoDB extension via PECL
RUN pecl install mongodb && docker-php-ext-enable mongodb

# 5. Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 6. Copy code
WORKDIR /var/www/html
COPY . .

# 7. Environment & Permissions
# Create uploads folder if missing
RUN mkdir -p uploads/complaints && chmod -R 775 uploads
RUN chown -R www-data:www-data /var/www/html

# 8. Start-up
EXPOSE 80
CMD ["apache2-foreground"]
