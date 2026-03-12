FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    curl \
    openssl \
    libpq-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libsodium-dev \
    && docker-php-ext-install pdo pdo_pgsql zip mbstring xml opcache sodium \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .
COPY docker/app/entrypoint.sh /usr/local/bin/app-entrypoint

RUN git config --global --add safe.directory /var/www/html
RUN composer install --no-interaction --prefer-dist --no-scripts
RUN sed -i 's/\r$//' /usr/local/bin/app-entrypoint && chmod +x /usr/local/bin/app-entrypoint

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
