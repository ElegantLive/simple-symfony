# syntax=docker/dockerfile:1
#
# Symfony 4.3 / PHP 7.x development image.
#
# Notes on the choices made here:
#   * PHP 7.3 + Debian bullseye: the dependency tree (Symfony 4.3, doctrine/*, the old
#     composer.lock) predates PHP 8 and is happiest on a glibc base image.
#   * Composer 2.2 LTS instead of latest: composer.lock is from the Flex 1.x era and
#     Composer 2.3+ is stricter about it.
#   * The packagist mirror is overridden *inside the image only*, so composer.json
#     (which still points at the dead packagist.phpcomposer.com) stays untouched.

FROM php:7.3-fpm-bullseye AS base

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    COMPOSER_HOME=/composer

# Composer 1.x, deliberately. composer.lock pins symfony/flex v1.4.5 and
# ocramius/package-versions 1.4.0, both of which declare
# "composer-plugin-api": "^1.0". Composer 2 provides 2.2.0, so it refuses the
# lock outright ("your lock file does not contain a compatible set of packages").
# Composer 1.10.27 provides 1.10.0 and resolves it.
#
# Note that the packagist override below is global config only. composer.json
# declares its own `repositories.packagist`, and a project's repositories take
# precedence over the global one - so this does NOT rescue the dead
# packagist.phpcomposer.com URL for this project. It is still set because it
# makes `composer require`/`update` in the container work once that repo entry
# is dealt with. Plain `composer install` does not need packagist at all: every
# dist URL in composer.lock points at github.com/api.github.com.
ARG COMPOSER_MIRROR=https://mirrors.aliyun.com/composer/

# bullseye is Debian's oldoldstable now and its security archive is broken: the
# Packages index still advertises versions (e.g. git 1:2.30.2-1+deb11u5) whose
# .deb files have been pruned from pool/updates, so any apt-get install that
# touches one of them dies with a wall of 404s. Verified against
# security.debian.org, deb.debian.org, aliyun and tuna - all agree and all 404.
# Dropping the suite makes apt resolve those packages from bullseye/main, which
# still serves them.
#
# Tradeoff: the image gets no security patches. That is acceptable for a local
# development image of an EOL PHP 7.3 stack; do not reuse this Dockerfile as the
# basis for a production image without pinning a working snapshot.debian.org
# date or moving to a supported PHP version.
RUN set -eux; \
    sed -i '/security\.debian\.org/d' /etc/apt/sources.list; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        libonig-dev \
        default-mysql-client \
        gosu \
    ; \
    rm -rf /var/lib/apt/lists/*

# ext-json / ext-ctype / ext-iconv are built into PHP; the rest are required by
# composer.json or by the bundles wired in config/bundles.php.
RUN set -eux; \
    docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        bcmath \
        intl \
        zip \
        opcache \
    ; \
    docker-php-ext-enable opcache

COPY --from=composer:1.10.27 /usr/bin/composer /usr/local/bin/composer

RUN set -eux; \
    composer --version; \
    if [ -n "$COMPOSER_MIRROR" ]; then \
        composer config -g repo.packagist composer "$COMPOSER_MIRROR"; \
    fi

# COMPOSER_HOME is created by the root build user above, so hand it to www-data:
# the entrypoint drops privileges before running composer, and composer needs to
# read config.json and write its cache there.
RUN chown -R www-data:www-data "$COMPOSER_HOME"

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/php/entrypoint.sh /usr/local/bin/app-entrypoint
# Deliberately outside /var/www/html: the app source is bind-mounted in dev, so a
# helper left in there would depend on the host checkout being mounted correctly.
COPY docker/php/wait-for-db.php /usr/local/bin/wait-for-db.php

# COPY preserves the source file's mode, and a checkout can easily carry 0600
# files. php-fpm runs as www-data, so a config file it cannot read makes FPM fail
# with "failed to open configuration file ... Permission denied" and the
# container crash-loops. Pin every mode here instead of trusting the checkout.
RUN chmod 0755 /usr/local/bin/app-entrypoint; \
    chmod 0644 \
        /usr/local/etc/php/conf.d/zz-app.ini \
        /usr/local/etc/php-fpm.d/zz-app.conf \
        /usr/local/bin/wait-for-db.php; \
    chown www-data:www-data /usr/local/etc/php-fpm.d/zz-app.conf /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# dev: code is bind-mounted by docker-compose, the image only needs the entrypoint.
# ---------------------------------------------------------------------------
FROM base AS dev

ENV APP_ENV=dev \
    APP_DEBUG=1

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# prod: dependencies and code are baked into the image, no bind mounts.
# Build with:
#   docker build --target prod -f docker/php/Dockerfile -t simple-app:prod .
# ---------------------------------------------------------------------------
FROM base AS prod

ENV APP_ENV=prod \
    APP_DEBUG=0

COPY composer.json composer.lock symfony.lock ./

# --no-scripts: the auto-scripts (cache:clear, assets:install) need the full app
# and a configured environment, so they run from the entrypoint instead.
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --no-scripts \
        --optimize-autoloader

COPY . .

RUN set -eux; \
    mkdir -p var/cache var/log public/uploads; \
    chown -R www-data:www-data var public/uploads

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
