FROM php:8.2-apache

# Enable mod_rewrite and set ServerName to suppress warning
RUN a2enmod rewrite
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Copy custom Apache config
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# Set working directory
WORKDIR /var/www/html

# Install mysqli extension for DB connectivity
RUN docker-php-ext-install mysqli pdo pdo_mysql

EXPOSE 80
