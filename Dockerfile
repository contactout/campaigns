# syntax=docker/dockerfile:1

# -----------------------------------------------------------------------------
# Stage 1: PHP dependencies (production)
# -----------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

RUN apk add --no-cache \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        linux-headers \
    && docker-php-ext-install intl zip

COPY composer.json composer.lock ./

RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --prefer-dist \
        --optimize-autoloader

COPY . .

RUN composer dump-autoload --optimize --no-dev --no-scripts

# -----------------------------------------------------------------------------
# Stage 2: Generate Wayfinder JS + build frontend assets
# -----------------------------------------------------------------------------
FROM php:8.4-cli-bookworm AS assets

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        ca-certificates \
        gnupg \
        unzip \
        git \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libonig-dev \
    && docker-php-ext-install intl zip \
    && rm -rf /var/lib/apt/lists/*

# Node.js is copied from the official image instead of piping a remote script to bash.
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && node --version \
    && npm --version

COPY --from=vendor /app /app

RUN mkdir -p \
        bootstrap/cache \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        storage/app/public \
    && php -r "file_exists('.env') || copy('.env.example', '.env');" \
    && php artisan package:discover --ansi --no-interaction \
    && php artisan key:generate --force --no-interaction \
    && php artisan wayfinder:generate --with-form --no-interaction

RUN npm ci \
    && npm run build \
    && rm -rf node_modules

# -----------------------------------------------------------------------------
# Stage 3: Runtime (Nginx + PHP-FPM)
# -----------------------------------------------------------------------------
FROM php:8.4-fpm-bookworm AS runtime

WORKDIR /var/www/html

ENV CONTAINER_ROLE=app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        nginx \
        supervisor \
        default-mysql-client \
        libc-client2007e-dev \
        libkrb5-dev \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        intl \
        gd \
        zip \
        opcache \
    && printf "\n" | pecl install imap \
    && docker-php-ext-enable opcache imap \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir -p /var/log/supervisor /run/nginx \
    && rm -f /etc/nginx/sites-enabled/default

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/php/www-override.conf /usr/local/etc/php-fpm.d/zz-www-override.conf
COPY docker/nginx.conf /etc/nginx/sites-available/campaigns
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN ln -s /etc/nginx/sites-available/campaigns /etc/nginx/sites-enabled/campaigns \
    && chmod +x /usr/local/bin/entrypoint.sh

COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build
COPY --from=assets /app/resources/js/actions /var/www/html/resources/js/actions
COPY --from=assets /app/resources/js/routes /var/www/html/resources/js/routes
COPY --from=assets /app/resources/js/wayfinder /var/www/html/resources/js/wayfinder

RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        storage/app/public \
        bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R ug+rwx storage bootstrap/cache

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
