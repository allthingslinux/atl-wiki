### TODO & NOTES ###
# volumes config
# healthcheck configs
# sitemap stuff
# compose setup for git extensions
# redis disk persistence
# https://www.mediawiki.org/wiki/Manual:Performance_tuning
# php.ini improvements

# Multi-stage build to reduce image size
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

# Final stage
FROM php:8.3-fpm-alpine AS final
SHELL ["/bin/ash", "-eo", "pipefail", "-c"]

LABEL maintainer="atmois@allthingslinux.org" \
      org.opencontainers.image.title="atl.wiki" \
      org.opencontainers.image.description="atl.wiki Docker Image"

# Build arguments
ARG MEDIAWIKI_MAJOR_VERSION
ARG MEDIAWIKI_VERSION
ARG MEDIAWIKI_BRANCH
ARG CITIZEN_VERSION

# Copy PHP extensions from builder
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Install runtime dependencies and setup directories
RUN --mount=type=cache,target=/var/cache/apk,sharing=locked \
    set -eux; \
    # Install runtime packages
    apk add --no-cache \
        nginx=1.28.0-r3 \
        imagemagick=7.1.2.0-r0 \
        librsvg=2.60.0-r0 \
        python3=3.12.11-r0 \
        git=2.49.1-r0 \
        ca-certificates=20250619-r0 \
        gnupg=2.4.7-r0 \
        icu-libs=76.1-r1 \
        oniguruma=6.9.10-r0 \
        libzip=1.11.4-r0 \
        libpng=1.6.47-r0 \
        libjpeg-turbo=3.1.0-r0 \
        freetype=2.13.3-r0 \
        unzip=6.0-r15; \
    # Setup directories
    mkdir -p /var/www/atlwiki/{mediawiki,cache} \
    chown -R nginx:nginx /var/www/atlwiki

# Copy Composer from official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

USER nginx
WORKDIR /var/www/atlwiki

# Install composer dependencies with cache mount
COPY --chown=nginx:nginx composer.json ./
RUN --mount=type=cache,target=/home/nginx/.composer,uid=82,gid=82 \
    composer install --no-dev --optimize-autoloader --no-scripts

# Download and verify MediaWiki in single layer
RUN --mount=type=cache,target=/tmp/mediawiki-cache,uid=82,gid=82 \
    set -eux; \
    mkdir -p mediawiki; \
    curl -fSL "https://releases.wikimedia.org/mediawiki/${MEDIAWIKI_MAJOR_VERSION}/mediawiki-${MEDIAWIKI_VERSION}.tar.gz" -o mediawiki.tar.gz; \
    curl -fSL "https://releases.wikimedia.org/mediawiki/${MEDIAWIKI_MAJOR_VERSION}/mediawiki-${MEDIAWIKI_VERSION}.tar.gz.sig" -o mediawiki.tar.gz.sig; \
    GNUPGHOME="$(mktemp -d)"; \
    export GNUPGHOME; \
    curl -fsSL "https://www.mediawiki.org/keys/keys.txt" | gpg --import; \
    gpg --batch --verify mediawiki.tar.gz.sig mediawiki.tar.gz; \
    tar -x --strip-components=1 -f mediawiki.tar.gz -C mediawiki; \
    # Cleanup
    gpgconf --kill all; \
    rm -rf "$GNUPGHOME" mediawiki.tar.gz.sig mediawiki.tar.gz \
           mediawiki/tests/ mediawiki/docs/ mediawiki/mw-config/

# Copy configuration files
COPY --chown=nginx:nginx LocalSettings.php mediawiki/
COPY --chown=nginx:nginx configs/ configs/
COPY --chown=nginx:nginx composer.local.json mediawiki/

# Install extensions and update composer dependencies
COPY --chown=nginx:nginx extensions.json install_extensions.py /tmp/
RUN --mount=type=cache,target=/home/nginx/.composer,uid=82,gid=82 \
    set -eux; \
    python3 /tmp/install_extensions.py; \
    cd mediawiki; \
    composer update --no-dev --optimize-autoloader; \
    # Install Citizen skin
    git clone --branch v${CITIZEN_VERSION} --single-branch --depth 1 \
        https://github.com/StarCitizenTools/mediawiki-skins-Citizen.git skins/Citizen; \
    # Cleanup
    rm -f /tmp/extensions.json /tmp/install_extensions.py \
    rm -rf skins/Citizen/.git*;

# Copy remaining files
COPY --chown=nginx:nginx robots.txt ./
COPY --chown=nginx:nginx .well-known ./.well-known
RUN ln -s /var/www/atlwiki/.well-known/security.txt /var/www/atlwiki/security.txt

USER root

# Copy configuration files and startup script
COPY mediawiki.conf /etc/nginx/http.d/
COPY php.ini /usr/local/etc/php/conf.d/custom.ini
COPY start.sh /start.sh
RUN chmod +x /start.sh

USER nginx
EXPOSE 80

CMD ["/start.sh"]
