FROM php:8.1-fpm

RUN apt-get update && apt-get install -y     git     zip     unzip     libpq-dev     libonig-dev     libxml2-dev     libzip-dev     curl     && docker-php-ext-install pdo pdo_pgsql zip mbstring xml opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install

CMD ["php-fpm"]
