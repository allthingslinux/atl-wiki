### TODO & NOTES ###
# volumes config
# healthcheck configs
# sitemap stuff
# compose setup for git extensions

# Multi-stage build to reduce image size
FROM php:8.3-fpm-alpine AS builder
SHELL ["/bin/ash", "-eo", "pipefail", "-c"]

# Environment Variables
ENV MEDIAWIKI_MAJOR_VERSION=1.43
ENV MEDIAWIKI_VERSION=1.43.3
ENV MEDIAWIKI_BRANCH=REL1_43
ENV CITIZEN_VERSION=3.5.0

RUN --mount=type=cache,target=/var/cache/apk,sharing=locked \
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
        unzip=6.0-r15 \
        git=2.49.1-r0 \
        ca-certificates=20250619-r0 \
        gnupg=2.4.7-r0 \
        make=4.4.1-r3 \
        gcc=14.2.0-r6 \
        g++=14.2.0-r6 \
        autoconf=2.72-r1 \
        pcre-dev=8.45-r4; \
    # Install PHP Extensions
    docker-php-ext-install -j"$(nproc)" \
        xml \
        mbstring \
        mysqli \
        pdo_mysql \
        intl \
        zip \
        calendar; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" gd; \
    pecl install apcu-5.1.22; \
    docker-php-ext-enable apcu; \
    pecl install redis; \
    docker-php-ext-enable redis; \
    # Cleanup
    docker-php-source delete; \
    rm -rf /tmp/pear ~/.pearrc; \
    apk del .build-deps

# Final stage
FROM php:8.3-fpm-alpine AS final
SHELL ["/bin/ash", "-eo", "pipefail", "-c"]

LABEL maintainer="atmois@allthingslinux.org"

# Environment Variables
ENV MEDIAWIKI_MAJOR_VERSION=1.43
ENV MEDIAWIKI_VERSION=1.43.3
ENV MEDIAWIKI_BRANCH=REL1_43
ENV CITIZEN_VERSION=3.5.0

# Copy PHP extensions from builder
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Install runtime dependencies
RUN --mount=type=cache,target=/var/cache/apk,sharing=locked \
    set -eux; \
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
        freetype=2.13.3-r0;

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Setup Directory
RUN mkdir -p /var/www/atlwiki/mediawiki && \
    mkdir -p /var/www/atlwiki/cache && \
    chown -R nginx:nginx /var/www/atlwiki
USER nginx

# Set ENV Variable Dependencies
COPY composer.json /var/www/atlwiki/composer.json
WORKDIR /var/www/atlwiki
RUN composer install --no-dev --optimize-autoloader

# Install Mediawiki
RUN set -eux; \
    curl -fSL "https://releases.wikimedia.org/mediawiki/${MEDIAWIKI_MAJOR_VERSION}/mediawiki-${MEDIAWIKI_VERSION}.tar.gz" -o mediawiki.tar.gz; \
    curl -fSL "https://releases.wikimedia.org/mediawiki/${MEDIAWIKI_MAJOR_VERSION}/mediawiki-${MEDIAWIKI_VERSION}.tar.gz.sig" -o mediawiki.tar.gz.sig; \
    GNUPGHOME="$(mktemp -d)"; \
    export GNUPGHOME; \
    curl -fsSL "https://www.mediawiki.org/keys/keys.txt" | gpg --import; \
    gpg --batch --verify mediawiki.tar.gz.sig mediawiki.tar.gz; \
    tar -x --strip-components=1 -f mediawiki.tar.gz -C /var/www/atlwiki/mediawiki; \
    gpgconf --kill all; \
    rm -r "$GNUPGHOME" mediawiki.tar.gz.sig mediawiki.tar.gz;


# Mediawiki Extension Dependencies
COPY composer.local.json /var/www/atlwiki/mediawiki/composer.local.json
WORKDIR /var/www/atlwiki/mediawiki
RUN composer update

# NGINX Configuration
COPY mediawiki.conf /etc/nginx/http.d/mediawiki.conf

# Custom PHP Configuration
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

# Website Files
COPY robots.txt /var/www/atlwiki/robots.txt
COPY .well-known /var/www/atlwiki/.well-known
RUN ln -s /var/www/atlwiki/.well-known/security.txt /var/www/atlwiki/security.txt

# Configs
COPY --chown=nginx:nginx LocalSettings.php /var/www/atlwiki/mediawiki/LocalSettings.php
COPY --chown=nginx:nginx configs/ /var/www/atlwiki/configs/

# Install MediaWiki Extensions dynamically
COPY extensions.json /tmp/extensions.json
COPY install_extensions.py /tmp/install_extensions.py
RUN set -eux; python3 /tmp/install_extensions.py

# Install Citizen Skin
RUN git clone --branch v${CITIZEN_VERSION} --single-branch --depth 1 https://github.com/StarCitizenTools/mediawiki-skins-Citizen.git /var/www/atlwiki/mediawiki/skins/Citizen

USER root

# Cleanup Files
RUN rm -f /tmp/extensions.json /tmp/install_extensions.py && \
    rm -rf /var/www/atlwiki/mediawiki/skins/Citizen/.git* && \
    rm -rf /var/www/atlwiki/mediawiki/tests/ && \
    rm -rf /var/www/atlwiki/mediawiki/docs/ && \
    rm -rf /var/www/atlwiki/mediawiki/.git* && \
    rm -rf /var/www/atlwiki/mediawiki/mw-config/

# Create Mediawiki Log File
RUN mkdir -p /var/log/mediawiki && \
    touch /var/log/mediawiki/debug.log && \
    chown -R nginx:nginx /var/log/mediawiki && \
    chmod -R 664 /var/log/mediawiki

# Startup Setup
COPY start.sh /start.sh
RUN chmod +x /start.sh

USER nginx
EXPOSE 80
CMD ["/start.sh"]
