FROM php:8.2-apache

RUN a2dismod mpm_event || true
RUN a2dismod mpm_worker || true
RUN a2enmod mpm_prefork

RUN docker-php-ext-install mysqli

COPY . /var/www/html/

RUN a2enmod rewrite

EXPOSE 80