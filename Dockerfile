### TODO & NOTES ###
# chown -R nginx:nginx extensions skins cache images
# volumes config
# healthcheck configs
# sudo chown -R atl-wiki:nginx /var/www/atlwiki && sudo chmod -R 750 /var/www/atlwiki
# sudo chmod -R 770 /var/www/atlwiki/images && sudo chmod -R 770 /var/www/atlwiki/cache
# sudo chmod -R 755 /var/www/atlwiki/sitemap && sudo chmod 755 /var/www/atlwiki/sitemap.xml
# compose setup for git extensions

# Multi-stage build to reduce image size
FROM php:8.3-fpm-alpine AS builder

# Environment Variables
ENV MEDIAWIKI_MAJOR_VERSION=1.43
ENV MEDIAWIKI_VERSION=1.43.3
ENV MEDIAWIKI_BRANCH=REL1_43
ENV CITIZEN_VERSION=3.5.0

# Install build dependencies for PHP extensions
RUN --mount=type=cache,target=/var/cache/apk,sharing=locked \
    set -eux; \
    apk add --no-cache --virtual .build-deps \
        libxml2-dev \
        oniguruma-dev \
        libzip-dev \
        icu-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        unzip \
        git \
        ca-certificates \
        gnupg \
        make \
        g++ \
        autoconf \
    ; \
    # Install PHP extensions
    docker-php-ext-install -j$(nproc) \
        xml \
        mbstring \
        mysqli \
        pdo_mysql \
        intl \
        zip \
    ; \
    # Configure GD properly
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j$(nproc) gd; \
    # Install APCu
    pecl install apcu; \
    docker-php-ext-enable apcu; \
    # Cleanup
    docker-php-source delete; \
    rm -rf /tmp/pear ~/.pearrc

# Final stage
FROM php:8.3-fpm-alpine AS final

LABEL maintainer="atmois@allthingslinux.org"

# Environment Variables
ENV MEDIAWIKI_MAJOR_VERSION=1.43
ENV MEDIAWIKI_VERSION=1.43.3
ENV MEDIAWIKI_BRANCH=REL1_43
ENV CITIZEN_VERSION=3.5.0

# Copy PHP extensions from builder
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Install runtime dependencies only
RUN --mount=type=cache,target=/var/cache/apk,sharing=locked \
    set -eux; \
    apk add --no-cache \
        nginx \
        imagemagick \
        librsvg \
        python3 \
        git \
        ca-certificates \
        gnupg \
        icu-libs \
        oniguruma \
        libzip \
        libpng \
        libjpeg-turbo \
        freetype \
    ;

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Setup Directory
RUN mkdir -p /var/www/atlwiki/mediawiki
RUN mkdir -p /var/www/atlwiki/images/tmp
RUN chown -R nginx:nginx /var/www/atlwiki

USER nginx

# Set up environment variables with phpdotenv
COPY composer.json /var/www/atlwiki/composer.json
RUN composer install --no-dev --optimize-autoloader --working-dir=/var/www/atlwiki

# Install Mediawiki
RUN set -eux; \
    curl -fSL "https://releases.wikimedia.org/mediawiki/${MEDIAWIKI_MAJOR_VERSION}/mediawiki-${MEDIAWIKI_VERSION}.tar.gz" -o mediawiki.tar.gz; \
    curl -fSL "https://releases.wikimedia.org/mediawiki/${MEDIAWIKI_MAJOR_VERSION}/mediawiki-${MEDIAWIKI_VERSION}.tar.gz.sig" -o mediawiki.tar.gz.sig; \
    export GNUPGHOME="$(mktemp -d)"; \
    curl -fsSL "https://www.mediawiki.org/keys/keys.txt" | gpg --import; \
    gpg --batch --verify mediawiki.tar.gz.sig mediawiki.tar.gz; \
    tar -x --strip-components=1 -f mediawiki.tar.gz -C /var/www/atlwiki/mediawiki; \
    gpgconf --kill all; \
    rm -r "$GNUPGHOME" mediawiki.tar.gz.sig mediawiki.tar.gz; \
    rm -rf /var/www/atlwiki/mediawiki/tests/ \
           /var/www/atlwiki/mediawiki/docs/ \
           /var/www/atlwiki/mediawiki/.git*;

# NGINX Configuration
COPY mediawiki.conf /etc/nginx/http.d/mediawiki.conf

# Custom PHP Configuration
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

# Website Files
COPY robots.txt /var/www/atlwiki/robots.txt
COPY .well-known /var/www/atlwiki/.well-known
RUN ln -s /var/www/atlwiki/.well-known/security.txt /var/www/atlwiki/security.txt

# Configs
COPY LocalSettings.php /var/www/atlwiki/mediawiki/LocalSettings.php
COPY configs/ /var/www/atlwiki/configs/

# Install MediaWiki Extensions dynamically
COPY extensions.json /tmp/extensions.json
COPY install_extensions.py /tmp/install_extensions.py
RUN set -eux; python3 /tmp/install_extensions.py

# Install Citizen Skin
RUN git clone --branch v${CITIZEN_VERSION} --single-branch --depth 1 https://github.com/StarCitizenTools/mediawiki-skins-Citizen.git /var/www/atlwiki/mediawiki/skins/Citizen

USER root

# Cleanup Files
RUN rm -rf /var/www/atlwiki/mediawiki/skins/Citizen/.git
RUN rm -f /tmp/extensions.json /tmp/install_extensions.py

# Startup Setup
COPY start.sh /start.sh
RUN chmod +x /start.sh

USER nginx
EXPOSE 80
CMD ["/start.sh"]
