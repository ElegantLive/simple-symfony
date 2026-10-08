#!/bin/sh
#
# First-time setup for the Docker development stack.
#
# This exists because of a startup-order trap: `vendor/` lives in a named volume
# (so composer never writes 10k files onto the macOS bind mount), which means a
# plain `docker compose up` on a fresh clone starts php-fpm with an empty vendor/
# and every request fails. Dependencies have to be installed into that volume once.
#
#   ./docker/setup.sh
#
# Re-running it is safe: `composer install` is idempotent and `up -d` is a no-op
# for services that are already running.

set -eu

cd "$(dirname "$0")/.."

COMPOSE=${COMPOSE:-docker compose}
APP_PORT=${APP_PORT:-8080}
MAILHOG_UI_PORT=${MAILHOG_UI_PORT:-8025}
DB_PORT=${DB_PORT:-3307}
DB_NAME=${DB_NAME:-simple}
DB_USER=${DB_USER:-root}
DB_PASSWORD=${DB_PASSWORD:-root}

echo "==> checking prerequisites"

if ! command -v docker >/dev/null 2>&1; then
    echo "docker is not installed or not on PATH" >&2
    exit 1
fi

if ! $COMPOSE version >/dev/null 2>&1; then
    echo "'$COMPOSE' is not available (needs Docker Compose v2, or set COMPOSE=docker-compose)" >&2
    exit 1
fi

# The JWT / signature private keys are gitignored, so a fresh clone does not have
# them and the login endpoints would 500 without them.
if [ ! -d config/certificate/jwt ]; then
    echo "config/certificate/jwt is missing." >&2
    echo "The JWT and signature keys are gitignored; copy them from another checkout" >&2
    echo "into config/certificate/{jwt,sign}/{rsa_private.pem,rsa_public.pem} first." >&2
    exit 1
fi

if [ ! -f .env ]; then
    echo "==> .env not found, creating it from .env.docker.example"
    cp .env.docker.example .env
fi

# --- 1. the image ----------------------------------------------------------
echo "==> building the php image (installs extensions, ~3-6 min the first time)"
$COMPOSE build app

# --- 2. the services composer/doctrine need ---------------------------------
# Brought up first so `composer install` has a working PDO connection for its
# script phase, and so the app's own entrypoint never waits on a cold database.
echo "==> starting db and redis"
# --wait needs Compose 2.1+; fall back for older clients.
$COMPOSE up -d --wait db redis 2>/dev/null || $COMPOSE up -d db redis

# --- 3. dependencies, inside the vendor volume ------------------------------
echo "==> installing composer dependencies into the vendor volume"
# --no-scripts: cache:clear / assets:install need a bootable app; the app
# container's entrypoint runs them once everything is up.
# Deliberately NOT passed -u www-data: the entrypoint must start as root to fix
# ownership of the vendor volume, and it drops to www-data itself before running
# composer. Passing -u here leaves the volume root-owned and the app unable to
# write its cache.
# composer.json's packagist URL is dead, but `composer install` takes every dist
# URL from composer.lock (all github.com), so it needs no repository metadata.
$COMPOSE run --rm --no-deps app \
    composer install --prefer-dist --no-interaction --no-progress --no-scripts

# --- 3b. schema -------------------------------------------------------------
# This deliberately does NOT use doctrine:migrations:migrate, because migrations
# were abandoned in this repository: src/Migrations/.gitignore contains `*`, so
# every migration added after 2020-03 was silently kept out of git, and the one
# stale migration that predated that rule (Version20190923042819) has been
# removed. What remains under src/Migrations is therefore a local-only,
# never-committed grab-bag of diffs taken against transient database states.
#
# The entity mapping is the source of truth. schema:update is safe to re-run (it
# executes only the difference), and it creates messenger_messages too, because
# the Doctrine messenger transport registers its own schema subscriber - so the
# worker gets its queue table without any extra step.
#
# `schema:update` reports "Nothing to update" once the database exists, so the
# step is a genuine no-op on every run after the first.
echo "==> bootstrapping the database schema from the entity mapping"
$COMPOSE run --rm --no-deps -e RUN_BOOT_TASKS=0 app \
    php bin/console doctrine:schema:update --force --env="${APP_ENV:-dev}" --no-interaction

# --- 4. everything else -----------------------------------------------------
echo "==> starting the stack"
$COMPOSE up -d

# --- 5. wait for the app to actually answer ---------------------------------
# The app container runs migrations + cache:warmup before it serves traffic, so
# poll until it responds instead of guessing.
if command -v curl >/dev/null 2>&1; then
    echo "==> waiting for http://localhost:${APP_PORT}"
    code=000
    i=0
    while [ "$i" -lt 90 ]; do
        code=$(curl -s -o /dev/null -w '%{http_code}' "http://localhost:${APP_PORT}/" || echo 000)
        case "$code" in
            200|301|302|401|403|404) break ;;
        esac
        i=$((i + 1))
        sleep 2
    done

    case "$code" in
        200|301|302|401|403|404) echo "    app is responding (HTTP $code)" ;;
        *) echo "    app did not answer within ~3min; check: $COMPOSE logs app" >&2 ;;
    esac
else
    echo "==> curl not found, skipping the readiness probe"
fi

cat <<EOF

==> done

  app       http://localhost:${APP_PORT}
  mailhog   http://localhost:${MAILHOG_UI_PORT}
  mysql     localhost:${DB_PORT}  (${DB_NAME} / ${DB_USER} / ${DB_PASSWORD})
  worker    $COMPOSE logs -f worker

Next: see docker/README.md, or 'make -f Makefile.docker help'
EOF
