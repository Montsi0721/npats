FROM php:8.2-apache

# Copy project files
COPY . /var/www/html/

# Enable Apache mod_rewrite (if needed)
RUN a2enmod rewrite

EXPOSE 80