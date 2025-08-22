### TODO & NOTES ###
# sitemap stuff
# https://www.mediawiki.org/wiki/Manual:Performance_tuning
# php.ini improvements
# https://www.mediawiki.org/wiki/Manual:$wgCdnReboundPurgeDelay

# Builder Stage
FROM php:8.3-fpm-alpine AS builder
SHELL ["/bin/ash", "-eo", "pipefail", "-c"]

# Install build dependencies and PHP extensions
RUN --mount=type=cache,target=/var/cache/apk,sharing=locked \
    --mount=type=cache,target=/tmp/pear,sharing=locked \
    set -eux; \
    # Install Build Dependencies
    apk add --no-cache --virtual .build-deps \
        libxml2-dev=2.13.8-r0 \
        oniguruma-dev=6.9.10-r0 \
        libzip-dev=1.11.4-r0 \
        icu-dev=76.1-r1 \
        libpng-dev=1.6.47-r0 \
        libjpeg-turbo-dev=3.1.0-r0 \
        freetype-dev=2.13.3-r0 \
        autoconf=2.72-r1 \
        pcre-dev=8.45-r4 \
        make=4.4.1-r3 \
        gcc=14.2.0-r6 \
        g++=14.2.0-r6; \
    # Install PHP Extensions
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        xml \
        mbstring \
        mysqli \
        pdo_mysql \
        intl \
        zip \
        calendar \
        gd; \
    # Install PECL extensions
    pecl install apcu-5.1.22 redis; \
    docker-php-ext-enable apcu redis; \
    # Cleanup in same layer
    docker-php-source delete; \
    rm -rf ~/.pearrc; \
    apk del .build-deps

# Mediawiki Setup Stage
FROM php:8.3-fpm-alpine AS mediawiki
SHELL ["/bin/ash", "-eo", "pipefail", "-c"]

# Build Arguments
ARG MEDIAWIKI_MAJOR_VERSION=1.43
ARG MEDIAWIKI_VERSION=1.43.3
ARG CITIZEN_VERSION=3.5.0
ARG MEDIAWIKI_BRANCH=REL1_43

# Install Mediawiki Install Dependencies
RUN --mount=type=cache,target=/var/cache/apk,sharing=locked \
    set -eux; \
    apk add --no-cache \
        python3=3.12.11-r0 \
        git=2.49.1-r0 \
        ca-certificates=20250619-r0 \
        gnupg=2.4.7-r0 \
        icu-libs=76.1-r1

# Copy PHP extensions from builder stage
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Copy Composer from Official Image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN mkdir -p /var/www/atlwiki/mediawiki;
WORKDIR /var/www/atlwiki/mediawiki

# Download and Verify MediaWiki
RUN --mount=type=cache,target=/tmp/mediawiki-cache \
    set -eux; \
    curl -fSL "https://releases.wikimedia.org/mediawiki/${MEDIAWIKI_MAJOR_VERSION}/mediawiki-${MEDIAWIKI_VERSION}.tar.gz" -o mediawiki.tar.gz; \
    curl -fSL "https://releases.wikimedia.org/mediawiki/${MEDIAWIKI_MAJOR_VERSION}/mediawiki-${MEDIAWIKI_VERSION}.tar.gz.sig" -o mediawiki.tar.gz.sig; \
    GNUPGHOME="$(mktemp -d)"; \
    export GNUPGHOME; \
    curl -fsSL "https://www.mediawiki.org/keys/keys.txt" | gpg --import; \
    gpg --batch --verify mediawiki.tar.gz.sig mediawiki.tar.gz; \
    tar -x --strip-components=1 -f mediawiki.tar.gz; \
    gpgconf --kill all; \
    rm -rf "$GNUPGHOME" mediawiki.tar.gz.sig mediawiki.tar.gz

# Copy Configuration Files
COPY composer.local.json ./composer.local.json

# Install Extensions Dynamically and Citizen Skin
COPY extensions.json install_extensions.py /tmp/
RUN --mount=type=cache,target=/root/.composer \
    set -eux; \
    python3 /tmp/install_extensions.py; \
    # Install Citizen skin
    git clone --branch v${CITIZEN_VERSION} --single-branch --depth 1 \
        https://github.com/StarCitizenTools/mediawiki-skins-Citizen.git /var/www/atlwiki/mediawiki/skins/Citizen;

# Install Extension Dependencies
RUN --mount=type=cache,target=/root/.composer \
    composer install --no-dev --optimize-autoloader --no-scripts

# Cleanup
RUN rm -rf /var/www/atlwiki/mediawiki/tests/ \
           /var/www/atlwiki/mediawiki/docs/ \
           /var/www/atlwiki/mediawiki/mw-config/ \
           /var/www/atlwiki/mediawiki/maintenance/dev/ \
           /var/www/atlwiki/mediawiki/maintenance/benchmarks/ \
           /var/www/atlwiki/mediawiki/includes/libs/composer/ \
           /var/www/atlwiki/mediawiki/vendor/*/tests/ \
           /var/www/atlwiki/mediawiki/vendor/*/test/ \
           /var/www/atlwiki/mediawiki/vendor/*/.git* \
           /var/www/atlwiki/mediawiki/skins/Citizen/.git* \
           /var/www/atlwiki/mediawiki/skins/*/tests/ \
           /var/www/atlwiki/mediawiki/extensions/*/tests/ && \
    find /var/www/atlwiki/mediawiki -name "*.md" -delete && \
    find /var/www/atlwiki/mediawiki -name "*.txt" -not -path "*/i18n/*" -delete && \
    rm -f /var/www/atlwiki/mediawiki/composer.local.json /var/www/atlwiki/mediawiki/composer.json /var/www/mediawiki/composer.lock

# Final Stage
FROM php:8.3-fpm-alpine AS final
SHELL ["/bin/ash", "-eo", "pipefail", "-c"]

LABEL maintainer="atmois@allthingslinux.org" \
      org.opencontainers.image.title="atl.wiki" \
      org.opencontainers.image.description="atl.wiki Docker Image"

# Install Runtime Dependencies
RUN --mount=type=cache,target=/var/cache/apk,sharing=locked \
    set -eux; \
    apk add --no-cache \
        imagemagick=7.1.2.0-r0 \
        librsvg=2.60.0-r0 \
        python3=3.12.11-r0 \
        icu-libs=76.1-r1 \
        oniguruma=6.9.10-r0 \
        libzip=1.11.4-r0 \
        libpng=1.6.47-r0 \
        libjpeg-turbo=3.1.0-r0 \
        freetype=2.13.3-r0 \
        unzip=6.0-r15;

# Copy PHP extensions from builder
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Setup Mediawiki user in final image to match ownership
RUN addgroup -g 1000 -S mediawiki && \
    adduser -u 1000 -S mediawiki -G mediawiki

RUN mkdir -p /var/www/atlwiki/mediawiki && \
    mkdir -p /var/www/atlwiki/cache && \
    chown -R mediawiki:mediawiki /var/www/atlwiki;

USER mediawiki
WORKDIR /var/www/atlwiki

# Copy pre-built mediawiki tree from the mediawiki build stage
COPY --chown=mediawiki:mediawiki --from=mediawiki /var/www/atlwiki .

# Copy Files
COPY --chown=mediawiki:mediawiki robots.txt ./robots.txt
COPY --chown=mediawiki:mediawiki .well-known ./.well-known
COPY --chown=mediawiki:mediawiki LocalSettings.php ./mediawiki/LocalSettings.php
COPY --chown=mediawiki:mediawiki configs/ ./configs/
RUN ln -s ./.well-known/security.txt ./security.txt

USER root
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

USER mediawiki
EXPOSE 9000

# Healthcheck
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD php-fpm -t || exit 1
