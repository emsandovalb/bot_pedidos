# syntax=docker/dockerfile:1

# ---- Assets (Vite) ----
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js tailwind.config.js postcss.config.js ./
RUN npm run build

# ---- PHP runtime ----
# serversideup/php trae nginx + php-fpm + supervisor listos para producción.
FROM serversideup/php:8.3-fpm-nginx AS app

# pdo_pgsql para PostgreSQL.
USER root
RUN install-php-extensions pdo_pgsql pgsql
USER www-data

WORKDIR /var/www/html

# Dependencias PHP primero (mejor cache de capas).
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# Código + assets compilados.
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev \
    && php artisan storage:link || true

ENV PHP_OPCACHE_ENABLE=1 \
    SSL_MODE=off \
    AUTORUN_ENABLED=false

# serversideup/php escucha en 8080.
EXPOSE 8080
