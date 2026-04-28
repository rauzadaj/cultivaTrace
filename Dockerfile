FROM composer:2 AS composer_deps

WORKDIR /app
COPY composer.json composer.lock symfony.lock ./
ARG INSTALL_DEV_DEPENDENCIES=0
RUN if [ "$INSTALL_DEV_DEPENDENCIES" = "1" ]; then \
      composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts; \
    else \
      composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts; \
    fi

FROM node:20-alpine AS frontend_build

WORKDIR /app/frontend
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

FROM php:8.4-fpm AS prod

RUN apt-get update && apt-get install -y \
    nginx \
    gettext-base \
    unzip \
    curl \
    openssl \
    libicu-dev \
    libpq-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libsodium-dev \
    && docker-php-ext-install intl pdo pdo_pgsql zip mbstring xml opcache sodium \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default /etc/nginx/conf.d/default.conf

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .
COPY --from=composer_deps /app/vendor ./vendor
COPY --from=frontend_build /app/frontend/dist/ ./public/
COPY docker/nginx/railway.conf.template /etc/nginx/templates/default.conf.template
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh \
    && mkdir -p var/cache var/log public

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD []
