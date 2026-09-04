# Production image for Smart Library.
#
# This is intentionally separate from compose.yaml, which is Laravel Sail's
# local development environment (see README.md "Local development" and
# ARCHITECTURE.md). Nothing here changes local `docker compose` behavior.
#
# Multi-stage build:
#   1. frontend  — installs npm deps and runs the Vite production build
#   2. vendor    — installs PHP deps (no-dev, optimized autoloader)
#   3. runtime   — a lean php-fpm + nginx image with only the built
#                  artifacts copied in; no Node, no Composer, no dev tooling
#
# Runtime configuration (APP_KEY, DB_*, AI_*, GOOGLE_*, etc.) comes entirely
# from the environment at container start — nothing is baked into the image,
# and no .env file is ever copied in. See docs/DEPLOYMENT.md.

# ---------------------------------------------------------------------------
# Stage 1: frontend build
# ---------------------------------------------------------------------------
FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY tsconfig.json vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
RUN npm run build

# ---------------------------------------------------------------------------
# Stage 2: PHP dependencies
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# ---------------------------------------------------------------------------
# Stage 3: runtime (php-fpm + nginx, supervised)
# ---------------------------------------------------------------------------
FROM php:8.5-fpm-alpine AS runtime

RUN apk add --no-cache \
        nginx \
        supervisor \
        libpq \
        icu-libs \
        oniguruma \
        gettext \
    && apk add --no-cache --virtual .build-deps \
        postgresql-dev \
        icu-dev \
        oniguruma-dev \
    && docker-php-ext-install pdo_pgsql \
    && docker-php-ext-install pgsql \
    && docker-php-ext-install intl \
    && docker-php-ext-install mbstring \
    && docker-php-ext-install bcmath \
    && apk del .build-deps
# NOTE: opcache is deliberately NOT compiled in here. On this php:8.5-fpm-alpine
# base image (PHP 8.5 is very new at time of writing), `docker-php-ext-install
# opcache` consistently fails at the final "Installing shared extensions" step
# ("cp: can't stat 'modules/*'") even built in isolation from every other
# extension — a toolchain/base-image issue, not something specific to this
# app. The app is fully correct without it, just without opcode caching (a
# performance optimization, not a functional requirement). Revisit once a
# fixed php:8.5-fpm-alpine tag is available, or build opcache from a pinned
# PECL release instead of the bundled source.

# Reasonable production PHP/OPcache defaults. No Xdebug, no dev-only extensions.
COPY docker/production/php.ini /usr/local/etc/php/conf.d/99-production.ini

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html
COPY --from=frontend /app/public/build /var/www/html/public/build

COPY docker/production/nginx.conf.template /etc/nginx/templates/nginx.conf.template
COPY docker/production/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/production/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Non-root application user. nginx's master process still starts as root
# (required to bind the listening port) but immediately drops its workers to
# this user via `user www-data;` in nginx.conf.template; php-fpm's pool runs
# its workers as this user directly (see php-fpm's www.conf default, which
# we leave in place — it already targets www-data on this base image).
RUN addgroup -g 1000 www-data 2>/dev/null; \
    adduser -D -u 1000 -G www-data www-data 2>/dev/null; \
    mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache \
             /var/lib/nginx/tmp /run/nginx \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
                                   /var/lib/nginx /run/nginx /var/log/nginx

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    PORT=8080

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD wget -q -O - "http://127.0.0.1:${PORT}/up" || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
