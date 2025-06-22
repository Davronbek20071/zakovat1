FROM php:8.1-apache

# PHP kengaytmalarini o‘rnatish
RUN docker-php-ext-install pdo pdo_mysql

# Composerni o‘rnatish
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Apache rootni o‘zgartirish
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

# Loyihani ichkariga nusxalash
COPY . /var/www/html

# Composer install
WORKDIR /var/www/html
RUN composer install

EXPOSE 80
CMD ["apache2-foreground"]
