# syntax=docker/dockerfile:1

FROM dunglas/frankenphp:1-php8.4 AS base

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


# ---- assets: 本番用フロントビルド ----
FROM node:22-alpine AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build


# ---- prod ----
FROM base AS prod
ENV APP_ENV=production APP_DEBUG=false

COPY . /app
COPY --from=assets /build/public/build /app/public/build

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache
