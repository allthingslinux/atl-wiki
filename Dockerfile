### TODO & NOTES ###
# chown -R www-data:www-data extensions skins cache images
# volumes config
# healthcheck configs
# sudo chown -R atl-wiki:www-data /var/www/atlwiki && sudo chmod -R 750 /var/www/atlwiki
# sudo chmod -R 770 /var/www/atlwiki/images && sudo chmod -R 770 /var/www/atlwiki/cache
# sudo chmod -R 755 /var/www/atlwiki/sitemap && sudo chmod 755 /var/www/atlwiki/sitemap.xml
# compose setup for git extensions

FROM php:8.3-fpm

LABEL maintainer="atmois@allthingslinux.org"

# Environment Variables
ENV MEDIAWIKI_MAJOR_VERSION=1.43
ENV MEDIAWIKI_VERSION=1.43.3
ENV MEDIAWIKI_BRANCH=REL1_43
ENV CITIZEN_VERSION=3.5.0

# Install system dependencies and PHP extensions
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    set -eux; \
	apt-get update; \
	apt-get install -y --no-install-recommends \
		nginx \
		imagemagick \
		librsvg2-bin \
		libxml2-dev \
		libonig-dev \
		libzip-dev \
		libicu-dev \
		libpng-dev \
		libjpeg-dev \
		libfreetype6-dev \
		unzip \
		python3 \
		git \
		ca-certificates \
		gnupg \
		dirmngr \
		; \
	docker-php-ext-install \
		xml \
		mbstring \
		mysqli \
		intl \
		gd \
		zip \
		; \
	pecl install apcu; \
	docker-php-ext-enable apcu; \
	rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Setup Directory's
RUN mkdir -p /var/www/atlwiki/mediawiki

# Install Mediawiki
RUN set -eux; \
	curl -fSL "https://releases.wikimedia.org/mediawiki/${MEDIAWIKI_MAJOR_VERSION}/mediawiki-${MEDIAWIKI_VERSION}.tar.gz" -o mediawiki.tar.gz; \
	curl -fSL "https://releases.wikimedia.org/mediawiki/${MEDIAWIKI_MAJOR_VERSION}/mediawiki-${MEDIAWIKI_VERSION}.tar.gz.sig" -o mediawiki.tar.gz.sig; \
	export GNUPGHOME="$(mktemp -d)"; \
	curl -fsSL "https://www.mediawiki.org/keys/keys.txt" | gpg --import; \
	gpg --batch --verify mediawiki.tar.gz.sig mediawiki.tar.gz; \
	tar -x --strip-components=1 -f mediawiki.tar.gz -C /var/www/atlwiki/mediawiki; \
	gpgconf --kill all; \
	rm -r "$GNUPGHOME" mediawiki.tar.gz.sig mediawiki.tar.gz;

# NGINX Configuration
COPY mediawiki.conf /etc/nginx/sites-available/mediawiki
RUN ln -s /etc/nginx/sites-available/mediawiki /etc/nginx/sites-enabled/mediawiki

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
RUN set -eux; \
	python3 /tmp/install_extensions.py

# Install Citizen Skin
RUN git clone https://github.com/StarCitizenTools/mediawiki-skins-Citizen.git --branch v${CITIZEN_VERSION} --single-branch --depth 1 /var/www/atlwiki/mediawiki/skins/Citizen

USER www-data
EXPOSE 80
