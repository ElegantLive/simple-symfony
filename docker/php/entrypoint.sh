#!/bin/sh
#
# Container entrypoint for the Symfony app (php-fpm, the messenger worker, and
# one-off commands such as `composer install`).
#
# Order of operations:
#   1. make the mounted directories writable
#   2. wait for MySQL            (only when the command needs a bootable app)
#   3. run migrations / warm the cache (only the web container, see RUN_BOOT_TASKS)
#   4. drop privileges and exec the real command
#
# It is meant to be started as root: step 1 needs root to fix ownership of the
# mounted volumes, and step 4 drops to www-data. Do not run it with
# `docker compose run -u www-data ...`; that skips the ownership fix and the
# named volumes stay root-owned, so the app cannot write its cache.
#
set -eu

APP_DIR=/var/www/html
cd "$APP_DIR"

# Does the command need the application (vendor/) to exist and the DB to be up?
# `composer install` is what *creates* vendor/, so it must be allowed to run
# before either is true.
needs_app=1
case "${1:-}" in
    composer|composer.phar|*/composer) needs_app=0 ;;
esac

# --- 1. permissions -------------------------------------------------------
# var/, vendor/ and public/uploads are volumes or bind mounts, so their
# ownership is not inherited from the image. Only the top-level directories are
# touched (never -R): recursive chown over vendor/ on every start would be slow
# and is unnecessary once composer has written into it as www-data.
mkdir -p var/cache var/log public/uploads 2>/dev/null || true
if [ "$(id -u)" = "0" ]; then
    chown www-data:www-data \
        var var/cache var/log public/uploads vendor 2>/dev/null || true
fi

is_empty_vendor=0
[ -f vendor/autoload.php ] || is_empty_vendor=1

# --- 2. wait for the database --------------------------------------------
if [ "$needs_app" = "1" ]; then
    # The DSN is parsed by the PHP helper, which reuses vendor/ and the app's own
    # Dotenv loading. With an empty vendor/ that would only die on the missing
    # autoloader, so fall back to the mysql client and its own parsing.
    db=""
    if [ "$is_empty_vendor" = "1" ]; then
        # mysql://user:pass@host:port/db?query -> host
        db="$(printf '%s' "${DATABASE_URL:-}" \
            | sed -e 's#^[a-zA-Z0-9+.-]*://##' -e 's#^[^@/]*@##' -e 's#[:/?].*$##')"
    else
        db="$(php /usr/local/bin/wait-for-db.php --print-host 2>/dev/null || true)"
    fi

    if [ "${WAIT_FOR_DB:-1}" = "1" ] && [ -n "$db" ]; then
        # A hostname that cannot be resolved can never become ready, so
        # distinguish "temporary" (retried) from "misconfigured" (fatal).
        if ! getent hosts "$db" >/dev/null 2>&1; then
            echo "[entrypoint] FATAL: database host '$db' does not resolve." >&2
            echo "[entrypoint]        Container-to-container hosts must be the service" >&2
            echo "[entrypoint]        name ('db'), not localhost." >&2
            exit 1
        fi

        echo "[entrypoint] waiting for database '$db' ..."
        if [ "$is_empty_vendor" = "1" ]; then
            until mysqladmin ping -h "$db" -u"${DB_USER:-root}" -p"${DB_PASSWORD:-root}" \
                    --silent --connect-timeout=2 >/dev/null 2>&1; do
                sleep 2
            done
        elif ! php /usr/local/bin/wait-for-db.php; then
            echo "[entrypoint] database did not become ready in time" >&2
            exit 1
        fi
    fi

    # Nothing below can work without the dependencies; fail with the actual
    # instruction instead of letting php-fpm emit a wall of missing-class errors.
    if [ "$is_empty_vendor" = "1" ]; then
        echo "[entrypoint] FATAL: /var/www/html/vendor/autoload.php is missing." >&2
        echo "[entrypoint]        vendor/ lives in a named volume, so a fresh clone must run:" >&2
        echo "[entrypoint]          ./docker/setup.sh      (or: make -f Makefile.docker setup)" >&2
        exit 1
    fi
fi

# --- 3. boot tasks -------------------------------------------------------
# Only the web container opts in (RUN_BOOT_TASKS=1), so a worker restart can
# never race with a schema migration. `needs_app` guards the one-off
# `compose run ... composer install` case: it inherits RUN_BOOT_TASKS=1 from the
# app service, and migrations cannot run before vendor/ exists.
if [ "$needs_app" = "1" ] && [ "${RUN_BOOT_TASKS:-0}" = "1" ]; then
    # --- 3a. schema -------------------------------------------------------
    # Both are opt-in and OFF by default, because this repository's migration
    # chain cannot build a fresh database: Version20190923042819 already creates
    # `user` and Version20200317144652 creates it again, so migrate always dies
    # with "Table 'user' already exists" and leaves a half-applied schema.
    # docker/setup.sh bootstraps the schema once from the entity mapping
    # instead; see docker/README.md. Failures here are warnings, not fatal, so a
    # bad schema never keeps the container from starting.
    if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
        echo "[entrypoint] applying migrations ..."
        if ! php bin/console doctrine:migrations:migrate \
                --no-interaction \
                --allow-no-migration \
                --env="${APP_ENV:-dev}"; then
            echo "[entrypoint] WARNING: migrations failed, continuing anyway" >&2
        fi
    fi

    if [ "${RUN_SCHEMA_UPDATE:-0}" = "1" ]; then
        echo "[entrypoint] syncing the schema from the entity mapping ..."
        if ! php bin/console doctrine:schema:update --force --env="${APP_ENV:-dev}"; then
            echo "[entrypoint] WARNING: doctrine:schema:update failed, continuing anyway" >&2
        fi
    fi

    # --- 3b. composer auto-scripts (assets:install) -----------------------
    # Off by default: the dev image already ran these at build time, and the prod
    # image has public/bundles missing (it is in .dockerignore), so it opts in.
    if [ "${RUN_COMPOSER_SCRIPTS:-0}" = "1" ]; then
        composer run-script auto-scripts --no-interaction || true
    fi

    # --- 3c. cache --------------------------------------------------------
    # No cache:clear here on purpose: it would throw away the container-local
    # cache on every restart and make the first request pay the full warmup.
    # A warmup failure is surfaced but must not stop the container: the app can
    # still build its cache on the first request.
    if [ "${RUN_CACHE_WARMUP:-1}" = "1" ]; then
        echo "[entrypoint] warming up the ${APP_ENV:-dev} cache ..."
        if ! php bin/console cache:warmup --env="${APP_ENV:-dev}"; then
            echo "[entrypoint] WARNING: cache:warmup failed, continuing anyway" >&2
        fi
    fi
fi

# --- 4. hand off ----------------------------------------------------------
# gosu is installed explicitly in the Dockerfile so this does not depend on
# whatever privilege-drop helper the base image happens to ship.
if [ "$#" -eq 0 ]; then
    echo "[entrypoint] no command given, defaulting to php-fpm" >&2
    set -- php-fpm
fi

echo "[entrypoint] starting: $*"

case "$1" in
    php-fpm|*/php-fpm)
        # php-fpm does its own privilege separation: the master must stay root,
        # and the pool workers run as www-data via `user`/`group` in the pool
        # config. Wrapping it in gosu breaks it - the stock image's logging goes
        # to /proc/self/fd/2, which a www-data master cannot open, so FPM dies
        # with "failed to open error_log ... Permission denied".
        exec "$@"
        ;;
esac

# Everything else (bin/console, composer, the messenger worker) has no business
# running as root.
if [ "$(id -u)" = "0" ]; then
    exec gosu www-data "$@"
fi

exec "$@"
