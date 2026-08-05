FROM php:8.4-fpm-bookworm

# Laravel 13's dependency tree requires PHP >= 8.4.1, so this image is 8.4 and not 8.3.

ARG UID=1000
ARG GID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev \
        libfreetype6-dev libwebp-dev libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        intl gd zip bcmath pdo_mysql opcache exif \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Run as the host user so files written from the container stay editable on the host.
RUN groupadd -g "${GID}" app 2>/dev/null || true \
    && useradd -u "${UID}" -g "${GID}" -m -s /bin/bash app 2>/dev/null || true

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-brator.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-brator.conf

WORKDIR /var/www/html
USER app
