# syntax=docker/dockerfile:1.7

# --- base: PHP-FPM с нужными расширениями, общий для всех стадий ---
FROM php:8.3-fpm-alpine AS base
RUN apk add --no-cache fcgi libpq rabbitmq-c libzip icu-libs \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev rabbitmq-c-dev libzip-dev icu-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql zip intl opcache \
    # amqp собирается из релиза на GitHub: зеркало PECL периодически недоступно и роняет CI.
    && mkdir -p /usr/src/php/ext/amqp \
    && curl -fsSL https://github.com/php-amqp/php-amqp/archive/refs/tags/v2.1.2.tar.gz | tar xz -C /usr/src/php/ext/amqp --strip-components=1 \
    && docker-php-ext-install -j"$(nproc)" amqp \
    && apk del .build-deps \
    && rm -rf /tmp/pear
ENV OPCACHE_VALIDATE_TIMESTAMPS=1
WORKDIR /app
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/fpm-healthcheck.conf /usr/local/etc/php-fpm.d/zz-healthcheck.conf

# --- vendor: только prod-зависимости, кешируется по composer.lock ---
FROM base AS vendor
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

# --- dev: зависимости для тестов, линтеров и PHPStan (код монтируется томом или копируется в CI) ---
FROM base AS dev
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
ENV APP_ENV=dev
COPY composer.json composer.lock ./
RUN composer install --prefer-dist --no-interaction --no-progress --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload \
    && mkdir -p var/cache storage/uploads storage/images \
    && chown -R www-data:www-data var storage

# --- prod: минимальный образ без dev-зависимостей и composer ---
FROM base AS prod
ENV APP_ENV=prod \
    OPCACHE_VALIDATE_TIMESTAMPS=0
COPY --from=vendor /app/vendor ./vendor
COPY bin ./bin
COPY config ./config
COPY migrations ./migrations
COPY public ./public
COPY src ./src
COPY openapi.yaml composer.json composer.lock ./
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --no-dev --classmap-authoritative \
    && rm /usr/bin/composer \
    && mkdir -p var/cache storage/uploads storage/images \
    && chown -R www-data:www-data var storage \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
USER www-data
HEALTHCHECK --interval=10s --timeout=3s --retries=5 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong || exit 1
