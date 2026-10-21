# syntax=docker/dockerfile:1

# PHP 8.4.27 security release; pin the multi-platform image to avoid stale tags.
FROM dunglas/frankenphp:1-php8.4@sha256:0de5a32944f72c13b45d183e8e6c53546b8172cec21cc8599e24b12e0661506d AS base

COPY docker/verify-php-runtime.php /usr/local/lib/verify-php-runtime.php
RUN printf "session.upload_progress.enabled = 0\n" > "$PHP_INI_DIR/conf.d/security.ini" \
    && php /usr/local/lib/verify-php-runtime.php \
    && frankenphp php-cli /usr/local/lib/verify-php-runtime.php

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions \
    bcmath \
    gd \
    intl \
    opcache \
    pcntl \
    pdo_pgsql \
    redis \
    zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
ENV SERVER_NAME=:80


# ---- dev: ソースは compose の volume でマウントする ----
FROM base AS dev


# ---- php-deps: 本番依存とWayfinder型生成 ----
FROM base AS php-deps
COPY . /app
RUN composer validate --strict \
    && composer install --no-dev --optimize-autoloader --no-interaction \
    && composer check-platform-reqs --no-dev \
    && php artisan wayfinder:generate --with-form


# ---- assets: 本番用フロントビルド ----
FROM node:22-alpine AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=php-deps /app/resources/js/actions /build/resources/js/actions
COPY --from=php-deps /app/resources/js/routes /build/resources/js/routes
COPY --from=php-deps /app/resources/js/wayfinder /build/resources/js/wayfinder
ENV SKIP_WAYFINDER_GENERATION=1
RUN npm run build


# ---- prod ----
FROM base AS prod
ENV APP_ENV=production APP_DEBUG=false

COPY --from=php-deps /app /app
COPY --from=assets /build/public/build /app/public/build
COPY docker/railway-entrypoint.sh /usr/local/bin/railway-entrypoint

RUN chmod +x /usr/local/bin/railway-entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["railway-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
