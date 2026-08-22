# syntax=docker/dockerfile:1

# LinkerLee production image.
#
# Build targets:
#   app  — PHP-FPM serving the application (also used for the queue worker)
#   web  — nginx with the compiled front-end assets, proxying PHP to `app`
#
# Both are produced from this one file so `docker compose up` needs nothing else.

ARG PHP_VERSION=8.4

# ---------------------------------------------------------------------------
# base — PHP with the extensions LinkerLee needs, and nothing more
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm-alpine AS base

RUN apk add --no-cache \
        fcgi \
        icu-libs \
        libzip \
        su-exec \
        tzdata \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && apk del .build-deps \
    && rm -rf /tmp/*

# pdo_sqlite and mbstring ship enabled in the official image, so SQLite works too.

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

WORKDIR /app

# ---------------------------------------------------------------------------
# vendor — PHP dependencies, production only
# ---------------------------------------------------------------------------
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Dependencies first, so a source-only change does not re-resolve them.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-progress

COPY . .
RUN composer install \
        --no-dev \
        --optimize-autoloader \
        --prefer-dist \
        --no-progress

# ---------------------------------------------------------------------------
# assets — Vite build. Needs PHP and vendor/ because the Wayfinder plugin
# shells out to `php artisan wayfinder:generate` during the build.
# ---------------------------------------------------------------------------
FROM base AS assets

# Alpine's nodejs package is Node 22, which is what package.json expects.
RUN apk add --no-cache nodejs npm

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY --from=vendor /app/vendor ./vendor
COPY . .

RUN npm run build

# ---------------------------------------------------------------------------
# app — the runtime image: PHP-FPM plus the application code
# ---------------------------------------------------------------------------
FROM base AS app

WORKDIR /var/www/html

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/php.ini "$PHP_INI_DIR/conf.d/zz-linkerlee.ini"
COPY docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-linkerlee.conf

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=vendor --chown=www-data:www-data /app/bootstrap/cache ./bootstrap/cache
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

COPY docker/entrypoint.sh /usr/local/bin/linkerlee-entrypoint
RUN chmod +x /usr/local/bin/linkerlee-entrypoint \
    && mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000

ENTRYPOINT ["linkerlee-entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# web — nginx serving public/ and proxying PHP to the app container
# ---------------------------------------------------------------------------
FROM nginx:1.29-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=assets /app/public /var/www/html/public

EXPOSE 80
