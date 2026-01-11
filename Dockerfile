# Copyright 2025 All Things Linux and Contributors

# Primary maintainer: Atmois <atmois@allthingslinux.org>

# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#     http://www.apache.org/licenses/LICENSE-2.0

# Builder Stage
FROM php:8.3-fpm-alpine AS builder
SHELL ["/bin/ash", "-eo", "pipefail", "-c"]

# Install PHP Extensions
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/

RUN --mount=type=cache,target=/tmp/phpexts-cache \
    set -eux && \
    install-php-extensions \
        apcu \
        calendar \
        exif \
        gd \
        intl \
        luasandbox \
        mbstring \
        mysqli \
        pdo_mysql \
        redis \
        wikidiff2 \
        xml \
        zip

# Mediawiki Setup Stage
FROM php:8.3-fpm-alpine AS mediawiki
SHELL ["/bin/ash", "-eo", "pipefail", "-c"]

# Build Arguments
ARG MEDIAWIKI_MAJOR_VERSION
ARG MEDIAWIKI_VERSION
ARG CITIZEN_VERSION
ARG MEDIAWIKI_BRANCH

RUN --mount=type=cache,target=/var/cache/apk,sharing=locked \
    set -eux && \
    apk add --no-cache \
        ca-certificates \
        freetype \
        git \
        gnupg \
        icu-libs \
        libjpeg-turbo \
        libpng \
        libthai \
        libxml2 \
        libzip \
        lua5.1-libs \
        oniguruma \
        python3

COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN mkdir -p /var/www/wiki/mediawiki
WORKDIR /var/www/wiki

COPY wiki/composer.json /var/www/wiki/composer.json
RUN --mount=type=cache,target=/root/.composer \
    composer install --no-dev --optimize-autoloader --no-scripts

WORKDIR /var/www/wiki/mediawiki

RUN --mount=type=cache,target=/tmp/mediawiki-cache \
    set -eux && \
    curl -fSL "https://releases.wikimedia.org/mediawiki/${MEDIAWIKI_MAJOR_VERSION}/mediawiki-${MEDIAWIKI_VERSION}.tar.gz" -o mediawiki.tar.gz && \
    curl -fSL "https://releases.wikimedia.org/mediawiki/${MEDIAWIKI_MAJOR_VERSION}/mediawiki-${MEDIAWIKI_VERSION}.tar.gz.sig" -o mediawiki.tar.gz.sig && \
    GNUPGHOME="$(mktemp -d)" && \
    export GNUPGHOME && \
    curl -fsSL "https://www.mediawiki.org/keys/keys.txt" | gpg --import && \
    gpg --batch --verify mediawiki.tar.gz.sig mediawiki.tar.gz && \
    tar -x --strip-components=1 -f mediawiki.tar.gz && \
    gpgconf --kill all && \
    rm -rf "$GNUPGHOME" mediawiki.tar.gz.sig mediawiki.tar.gz

# Install Additional Dependencies

COPY wiki/extensions.json wiki/install_extensions.py /tmp/
RUN --mount=type=cache,target=/root/.composer \
    set -eux && \
    python3 /tmp/install_extensions.py && \
    # Install Citizen skin
    git clone --branch v${CITIZEN_VERSION} --single-branch --depth 1 \
        https://github.com/StarCitizenTools/mediawiki-skins-Citizen.git /var/www/wiki/mediawiki/skins/Citizen

COPY wiki/composer.local.json ./composer.local.json
RUN --mount=type=cache,target=/root/.composer \
    composer update --no-dev --optimize-autoloader --no-scripts

# Cleanup
RUN rm -rf /var/www/wiki/mediawiki/tests/ \
        /var/www/wiki/mediawiki/docs/ \
        /var/www/wiki/mediawiki/mw-config/ \
        /var/www/wiki/mediawiki/maintenance/dev/ \
        /var/www/wiki/mediawiki/maintenance/benchmarks/ \
        /var/www/wiki/mediawiki/vendor/*/tests/ \
        /var/www/wiki/mediawiki/vendor/*/test/ \
        /var/www/wiki/mediawiki/vendor/*/.git* \
        /var/www/wiki/mediawiki/skins/Citizen/.git* \
        /var/www/wiki/mediawiki/skins/*/tests/ \
        /var/www/wiki/mediawiki/extensions/*/tests/ && \
    find /var/www/wiki/mediawiki -name "*.md" -delete && \
    find /var/www/wiki/mediawiki -name "*.txt" -not -path "*/i18n/*" -delete && \
    rm -f /var/www/wiki/mediawiki/composer.local.json /var/www/wiki/mediawiki/composer.lock

# Final Stage
FROM php:8.3-fpm-alpine AS final
SHELL ["/bin/ash", "-eo", "pipefail", "-c"]

LABEL maintainer="atmois@allthingslinux.org" \
      org.opencontainers.image.title="atl.wiki" \
      org.opencontainers.image.description="atl.wiki Docker Image"

# Install Runtime Dependencies
RUN --mount=type=cache,target=/var/cache/apk,sharing=locked \
    set -eux && \
    apk add --no-cache \
        freetype \
        icu-libs \
        imagemagick \
        libavif \
        libjpeg-turbo \
        libpng \
        librsvg \
        libthai \
        libxml2 \
        libzip \
        lua5.1-libs \
        lz4-libs \
        oniguruma \
        python3 \
        rsvg-convert \
        unzip

COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Setup Mediawiki user in final image to match ownership
RUN addgroup -g 1000 -S mediawiki && \
    adduser -u 1000 -S mediawiki -G mediawiki

RUN mkdir -p /var/www/wiki/mediawiki && \
    mkdir -p /var/www/wiki/cache && \
    mkdir -p /var/www/wiki/sitemap && \
    touch /var/www/wiki/sitemap/sitemap-index-atl.wiki.xml && \
    ln -s /var/www/wiki/sitemap/sitemap-index-atl.wiki.xml /var/www/wiki/sitemap.xml && \
    chown -R mediawiki:mediawiki /var/www/wiki && \
    chmod -R 775 /var/www/wiki/sitemap && \
    chmod -R 770 /var/www/wiki/cache

USER mediawiki
WORKDIR /var/www/wiki

COPY --chown=mediawiki:mediawiki --from=mediawiki /var/www/wiki .

COPY --chown=mediawiki:mediawiki wiki/robots.txt ./robots.txt
COPY --chown=mediawiki:mediawiki wiki/.well-known ./.well-known
COPY --chown=mediawiki:mediawiki wiki/LocalSettings.php ./mediawiki/LocalSettings.php
COPY --chown=mediawiki:mediawiki wiki/configs/ ./configs/
RUN ln -s ./.well-known/security.txt ./security.txt

USER root
COPY wiki/php.ini /usr/local/etc/php/conf.d/custom.ini

USER mediawiki

# Fix MWCallbackStream.php return type declaration (TEMPORARY until Upstream Fixes it)
RUN sed -i "s/public function write( \$string ) {/public function write( \$string ): int {/" /var/www/wiki/mediawiki/includes/http/MWCallbackStream.php

# Expose Port for FastCGI
EXPOSE 9000

# Healthcheck
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD ["php-fpm", "-t"]
