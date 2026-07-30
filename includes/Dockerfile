# Use the official PHP 8.3 image with Apache web server
FROM php:8.3-apache

# Install the mysqli extension so PHP can connect to Aiven
RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable mysqli

# Copy your project files into the container's web directory
COPY . /var/www/html/

# Update Apache configuration to use the PORT environment variable provided by Render
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Set proper permissions for the web server
RUN chown -R www-data:www-data /var/www/html