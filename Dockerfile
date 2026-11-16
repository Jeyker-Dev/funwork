# syntax=docker/dockerfile:1

# Production image: Laravel Octane on FrankenPHP, serving plain HTTP on port 8000.
# TLS is terminated by the standalone Caddy service in compose.prod.yml.
# Local development uses Laravel Sail (compose.yaml), not this file.

# --- Stage 1: PHP dependencies -------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# Flux Pro credentials are mounted as a build secret so they never end up in an image layer.
RUN --mount=type=secret,id=composer_auth,target=/app/auth.json \
    composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --no-scripts \
        --no-autoloader \
        --ignore-platform-reqs

COPY . .

RUN composer dump-autoload --no-dev --optimize --no-scripts

# --- Stage 2: frontend assets --------------------------------------------------
FROM node:22-bookworm-slim AS assets

WORKDIR /app

COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN corepack enable
RUN pnpm install --frozen-lockfile

COPY . .
# app.css imports Flux styles from vendor/.
COPY --from=vendor /app/vendor ./vendor

RUN pnpm run build

# --- Stage 3: runtime ----------------------------------------------------------
FROM dunglas/frankenphp:1-php8.4 AS app

RUN install-php-extensions pcntl pdo_mysql intl zip bcmath opcache gd exif

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /app

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

ARG USER=appuser

# Run as a non-root user; port 8000 needs no extra capabilities.
RUN useradd --create-home --shell /bin/sh ${USER} \
    && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
    && php artisan package:discover --ansi \
    && chmod +x /usr/local/bin/entrypoint \
    && chown -R ${USER}:${USER} storage bootstrap/cache /data/caddy /config/caddy

USER ${USER}

ENTRYPOINT ["entrypoint"]

CMD ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=8000", "--admin-host=127.0.0.1"]
