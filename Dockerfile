FROM php:8.1-apache

# 🔧 Zaruriy paketlar: unzip, zip, git, libzip
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    zip \
    libzip-dev \
    && docker-php-ext-install zip pdo pdo_mysql

# ✅ Composer o‘rnatish
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 📁 Apache root-ni public papkaga sozlash
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

# 📦 Loyihani ichkariga nusxalash
COPY . /var/www/html

WORKDIR /var/www/html

# 📦 Composer install — pluginlarsiz xavfsiz usulda
RUN composer install --no-interaction --no-plugins --no-scripts

# 🌐 Port ochish
EXPOSE 80

# 🚀 Apache ishga tushirish
CMD ["apache2-foreground"]
