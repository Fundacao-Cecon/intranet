ARG PHP_IMAGE=php:8.4-fpm-bookworm
ARG COMPOSER_IMAGE=composer:2
FROM ${COMPOSER_IMAGE} AS composer_binary
FROM ${PHP_IMAGE} AS development
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev libonig-dev libicu-dev \
    && docker-php-ext-install -j2 pdo_pgsql mbstring zip intl opcache \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer_binary /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/intranet-uploads.ini
COPY docker/php/entrypoint.sh /usr/local/bin/intranet-entrypoint
RUN chmod +x /usr/local/bin/intranet-entrypoint
ENTRYPOINT ["intranet-entrypoint"]
CMD ["php-fpm"]
