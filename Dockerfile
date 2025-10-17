#  Copyright 2025 atmois <atmois@allthingslinux.org>
#
#  Licensed under the Apache License, Version 2.0 (the "License");
#  you may not use this file except in compliance with the License.
#  You may obtain a copy of the License at
#      http://www.apache.org/licenses/LICENSE-2.0

# Builder Stage
FROM php:8.3-fpm-alpine AS builder
SHELL ["/bin/ash", "-eo", "pipefail", "-c"]

RUN --mount=type=cache,target=/var/cache/apk,sharing=locked \
    --mount=type=cache,target=/tmp/pear,sharing=locked \
    set -eux && \
    # Install Build Dependencies
    apk add --no-cache --virtual .build-deps \
        libxml2-dev \
        oniguruma-dev \
        libzip-dev \
        icu-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        autoconf \
        pcre-dev \
        make \
        gcc \
        g++ \
        git \
        lua5.1-dev; \
    # Install PHP Extensions
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j"$(nproc)" \
        xml \
        mbstring \
        mysqli \
        pdo_mysql \
        intl \
        zip \
        calendar \
        gd \
        exif; \
    # Install PECL extensions
    pecl install redis luasandbox && \
    docker-php-ext-enable redis luasandbox && \
    # Cleanup in same layer
    docker-php-source delete && \
    rm -rf ~/.pearrc && \
    apk del .build-deps

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
        python3 \
        git \
        ca-certificates \
        gnupg \
        icu-libs \
        libzip \
        libpng \
        libjpeg-turbo \
        freetype \
        libxml2 \
        oniguruma \
        lua5.1-libs

COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN mkdir -p /var/www/atlwiki/mediawiki
WORKDIR /var/www/atlwiki

COPY wiki/composer.json /var/www/atlwiki/composer.json
RUN --mount=type=cache,target=/root/.composer \
    composer install --no-dev --optimize-autoloader --no-scripts

WORKDIR /var/www/atlwiki/mediawiki

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
        https://github.com/StarCitizenTools/mediawiki-skins-Citizen.git /var/www/atlwiki/mediawiki/skins/Citizen

COPY wiki/composer.local.json ./composer.local.json
RUN --mount=type=cache,target=/root/.composer \
    composer update --no-dev --optimize-autoloader --no-scripts

# Cleanup
RUN rm -rf /var/www/atlwiki/mediawiki/tests/ \
        /var/www/atlwiki/mediawiki/docs/ \
        /var/www/atlwiki/mediawiki/mw-config/ \
        /var/www/atlwiki/mediawiki/maintenance/dev/ \
        /var/www/atlwiki/mediawiki/maintenance/benchmarks/ \
        /var/www/atlwiki/mediawiki/vendor/*/tests/ \
        /var/www/atlwiki/mediawiki/vendor/*/test/ \
        /var/www/atlwiki/mediawiki/vendor/*/.git* \
        /var/www/atlwiki/mediawiki/skins/Citizen/.git* \
        /var/www/atlwiki/mediawiki/skins/*/tests/ \
        /var/www/atlwiki/mediawiki/extensions/*/tests/ && \
    find /var/www/atlwiki/mediawiki -name "*.md" -delete && \
    find /var/www/atlwiki/mediawiki -name "*.txt" -not -path "*/i18n/*" -delete && \
    rm -f /var/www/atlwiki/mediawiki/composer.local.json /var/www/atlwiki/mediawiki/composer.lock

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
        imagemagick \
        librsvg \
        rsvg-convert \
        python3 \
        icu-libs \
        oniguruma \
        libzip \
        libpng \
        libjpeg-turbo \
        freetype \
        unzip \
        lua5.1-libs \
        libxml2

COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Setup Mediawiki user in final image to match ownership
RUN addgroup -g 1000 -S mediawiki && \
    adduser -u 1000 -S mediawiki -G mediawiki

RUN mkdir -p /var/www/atlwiki/mediawiki && \
    mkdir -p /var/www/atlwiki/cache && \
    mkdir -p /var/www/atlwiki/sitemap && \
    touch /var/www/atlwiki/sitemap/sitemap-index-atl.wiki.xml && \
    ln -s /var/www/atlwiki/sitemap/sitemap-index-atl.wiki.xml /var/www/atlwiki/sitemap.xml && \
    chown -R mediawiki:mediawiki /var/www/atlwiki && \
    chmod -R 775 /var/www/atlwiki/sitemap && \
    chmod -R 770 /var/www/atlwiki/cache

USER mediawiki
WORKDIR /var/www/atlwiki

COPY --chown=mediawiki:mediawiki --from=mediawiki /var/www/atlwiki .

COPY --chown=mediawiki:mediawiki wiki/robots.txt ./robots.txt
COPY --chown=mediawiki:mediawiki wiki/.well-known ./.well-known
COPY --chown=mediawiki:mediawiki wiki/LocalSettings.php ./mediawiki/LocalSettings.php
COPY --chown=mediawiki:mediawiki wiki/configs/ ./configs/
RUN ln -s ./.well-known/security.txt ./security.txt

USER root
COPY wiki/php.ini /usr/local/etc/php/conf.d/custom.ini

USER mediawiki

# Fix MWCallbackStream.php return type declaration (TEMPORARY until Upstream Fixes it)
RUN sed -i "s/public function write( \$string ) {/public function write( \$string ): int {/" /var/www/atlwiki/mediawiki/includes/http/MWCallbackStream.php

# Expose Port for FastCGI
EXPOSE 9000

# Healthcheck
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD ["php-fpm", "-t"]
