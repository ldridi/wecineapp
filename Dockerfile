# Dockerfile

# Base image with PHP 8.3 and Apache
FROM php:8.3-apache

# Set the working directory inside the container
WORKDIR /var/www/html

# Install necessary PHP extensions and utilities
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd

# Enable Apache mod_rewrite for Symfony routing
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js and npm
RUN curl -sL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs

# Set proper permissions for Symfony
RUN chown -R www-data:www-data /var/www/html

# Expose Apache port
EXPOSE 80

# Copy custom Apache configuration
COPY ./docker/vhost.conf /etc/apache2/sites-available/000-default.conf

# Start Apache server
CMD ["apache2-foreground"]
