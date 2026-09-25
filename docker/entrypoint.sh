#!/usr/bin/env sh
set -eu

ROLE="${CONTAINER_ROLE:-app}"

log() {
    printf '%s\n' "[entrypoint] $*"
}

wait_for_db() {
    if [ -z "${DB_HOST:-}" ] || [ "${DB_CONNECTION:-}" = "sqlite" ]; then
        return 0
    fi

    log "Waiting for database at ${DB_HOST}:${DB_PORT:-3306}..."
    i=0
    export MYSQL_PWD="${DB_PASSWORD:-}"
    until mysqladmin ping \
        -h"${DB_HOST}" \
        -P"${DB_PORT:-3306}" \
        -u"${DB_USERNAME:-root}" \
        --silent
    do
        i=$((i + 1))
        if [ "$i" -ge 60 ]; then
            log "Database did not become ready in time."
            exit 1
        fi
        sleep 2
    done
    unset MYSQL_PWD
    log "Database is ready."
}

cd /var/www/html

# Ensure writable runtime dirs exist (named volumes may replace image dirs).
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/public \
    bootstrap/cache

if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache || true
fi

wait_for_db

if [ -z "${APP_KEY:-}" ]; then
    log "APP_KEY is empty. Set it in .env before starting."
    log "Generate one with:  echo \"base64:\$(openssl rand -base64 32)\""
    exit 1
fi

if [ "$ROLE" = "app" ]; then
    log "Running migrations..."
    php artisan migrate --force --no-interaction

    if [ ! -L public/storage ] && [ ! -e public/storage ]; then
        php artisan storage:link --no-interaction || true
    fi
fi

log "Caching config / routes / views..."
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction || log "route:cache skipped"
php artisan view:cache --no-interaction || log "view:cache skipped"

log "Starting (${ROLE}): $*"
exec "$@"
