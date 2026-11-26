FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    curl-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    icu-dev \
    $PHPIZE_DEPS

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        curl \
        zip \
        gd \
        mbstring \
        intl \
        bcmath \
        opcache \
        pcntl

RUN pecl install redis && docker-php-ext-enable redis

RUN addgroup -g 1000 -S fork_panel_xc \
    && adduser -u 1000 -S fork_panel_xc -G fork_panel_xc

WORKDIR /home/fork_panel_xc

COPY --chown=fork_panel_xc:fork_panel_xc console.php ./console.php

RUN mkdir -p storage/logs storage/cache storage/sessions \
    && chown -R fork_panel_xc:fork_panel_xc storage

COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

USER fork_panel_xc

EXPOSE 9000

CMD ["php-fpm"]
