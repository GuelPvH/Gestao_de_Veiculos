# syntax=docker/dockerfile:1

FROM php:8.5-apache-bookworm AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl libicu-dev libonig-dev libsqlite3-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" \
        intl mbstring opcache pdo_mysql pdo_sqlite zip \
    && rm -rf /var/lib/apt/lists/*

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && a2enmod rewrite headers \
    && printf 'ServerName localhost\n' > /etc/apache2/conf-available/server-name.conf \
    && a2enconf server-name \
    && sed -ri 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

FROM php-base AS vendor

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.10 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./

RUN composer install \
    --no-dev --no-scripts --no-plugins --no-autoloader \
    --no-interaction --no-progress --prefer-dist

COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY routes ./routes
COPY artisan ./artisan

RUN composer dump-autoload --no-dev --optimize --no-scripts --no-plugins

FROM node:26-bookworm-slim AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts --no-audit --no-fund
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM php-base AS runtime

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /var/www/html/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

RUN install -d -m 775 -o www-data -g www-data \
        /var/run/apache2 /var/lock/apache2 /var/log/apache2 \
        storage/app/private storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/testing storage/framework/views \
        storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=3s --start-period=30s --retries=3 \
    CMD curl --fail --silent http://127.0.0.1:8080/up || exit 1

CMD ["apache2-foreground"]
