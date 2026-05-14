FROM php:8.4-fpm-alpine

RUN apk add --no-cache bash git icu-dev libzip-dev oniguruma-dev \
    && docker-php-ext-install intl mbstring opcache pdo_mysql zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html
