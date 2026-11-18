#!/bin/sh
set -e

cd /var/www/html

log() {
    echo "[stats-radar] $*"
}

# Make sure runtime directories exist (they live on a named volume in Docker).
mkdir -p \
    bootstrap/cache \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# ---------------------------------------------------------------------------
# Application key
# ---------------------------------------------------------------------------
# On a fresh install APP_KEY is empty. Generating a key into .env would be lost
# with the image, so we persist it inside the storage volume and export it for
# PHP-FPM workers (the official image sets clear_env = no).
if [ -z "${APP_KEY:-}" ]; then
    KEY_FILE="storage/app/.app_key"

    if [ -f "$KEY_FILE" ]; then
        APP_KEY="$(cat "$KEY_FILE")"
    else
        APP_KEY="base64:$(head -c 32 /dev/urandom | base64 | tr -d '\n')"
        printf '%s' "$APP_KEY" > "$KEY_FILE"
        chown www-data:www-data "$KEY_FILE" 2>/dev/null || true
        log "Generated a new application key."
    fi

    export APP_KEY
fi

# ---------------------------------------------------------------------------
# Wait for PostgreSQL
# ---------------------------------------------------------------------------
if [ "${DB_CONNECTION:-pgsql}" = "pgsql" ] && [ -n "${DB_HOST:-}" ]; then
    log "Waiting for PostgreSQL at ${DB_HOST}:${DB_PORT:-5432}..."
    ATTEMPTS=0

    until php -r '
        try {
            new PDO(
                sprintf("pgsql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT") ?: "5432", getenv("DB_DATABASE")),
                getenv("DB_USERNAME"),
                getenv("DB_PASSWORD")
            );
        } catch (Throwable $e) {
            exit(1);
        }
    '; do
        ATTEMPTS=$((ATTEMPTS + 1))
        if [ "$ATTEMPTS" -ge 30 ]; then
            log "PostgreSQL is not reachable, giving up."
            exit 1
        fi
        sleep 2
    done
fi

# ---------------------------------------------------------------------------
# Database migrations (only the main app container runs them)
# ---------------------------------------------------------------------------
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    log "Running database migrations..."
    su-exec www-data php artisan migrate --force
fi

if [ ! -L public/storage ]; then
    su-exec www-data php artisan storage:link >/dev/null 2>&1 || true
fi

# ---------------------------------------------------------------------------
# Production optimizations
# ---------------------------------------------------------------------------
if [ "${APP_ENV:-production}" = "production" ]; then
    log "Caching configuration, routes and views..."
    su-exec www-data php artisan config:cache
    su-exec www-data php artisan route:cache
    su-exec www-data php artisan view:cache
else
    su-exec www-data php artisan optimize:clear >/dev/null 2>&1 || true
fi

log "Starting: $*"
exec "$@"
