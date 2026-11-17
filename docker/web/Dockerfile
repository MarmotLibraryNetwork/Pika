# Pika web/app image: Apache + mod_php + Java 8 (for the reindexer, grouping and
# extract jars). Mirrors PikaSettings bash/pika-server-setup/pika-server-setup-php8.sh
# and pika-pika-install.sh. The Pika source itself is not copied in; docker-compose.yml
# bind-mounts the checkout at /usr/local/pika.
FROM ubuntu:24.04

ARG PHP_VERSION=8.5
ARG DEBIAN_FRONTEND=noninteractive
ENV PHP_VERSION=${PHP_VERSION} \
	COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
	&& apt-get install -y --no-install-recommends software-properties-common ca-certificates gnupg \
	&& add-apt-repository -y ppa:ondrej/php \
	&& apt-get update \
	&& apt-get install -y --no-install-recommends \
		apache2 \
		libapache2-mod-php${PHP_VERSION} \
		php${PHP_VERSION} \
		php${PHP_VERSION}-cli \
		php${PHP_VERSION}-bz2 \
		php${PHP_VERSION}-curl \
		php${PHP_VERSION}-gd \
		php${PHP_VERSION}-mbstring \
		php${PHP_VERSION}-mysql \
		php${PHP_VERSION}-pgsql \
		php${PHP_VERSION}-soap \
		php${PHP_VERSION}-xml \
		php${PHP_VERSION}-zip \
		php${PHP_VERSION}-memcache \
		php${PHP_VERSION}-memcached \
		php${PHP_VERSION}-xdebug \
		openjdk-8-jdk-headless \
		mariadb-client \
		curl \
		git \
		unzip \
		tzdata \
	&& rm -rf /var/lib/apt/lists/*

RUN a2enmod php${PHP_VERSION} rewrite ssl headers \
	&& a2dissite 000-default \
	&& phpenmod -v ${PHP_VERSION} memcache memcached

# PHP dependencies. composer.json sets vendor-dir to /usr/share/composer/vendor,
# which is where web/bootstrap.php looks for the autoloader.
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY install/composer.json install/composer.lock /tmp/pika-install/
RUN mkdir -p /usr/share/composer \
	&& composer install --working-dir=/tmp/pika-install --no-interaction --no-progress \
	&& rm -rf /tmp/pika-install /root/.composer

# Pika's patched PEAR DB_DataObject goes over the top of the composer copy
COPY install/PEAR/DB/ /usr/share/composer/vendor/pear/db/DB/

# Legacy vufind-plus paths still referenced by site configs and vhosts
RUN mkdir -p /usr/local/pika /data/pika /var/log/pika /run/mysqld \
	&& chmod 1777 /run/mysqld \
	&& ln -s /usr/local/pika /usr/local/vufind-plus \
	&& ln -s /data/pika /data/vufind-plus \
	&& ln -s /var/log/pika /var/log/vufind-plus

COPY docker/web/xdebug.ini /etc/php/${PHP_VERSION}/mods-available/pika-xdebug.ini
RUN phpenmod -v ${PHP_VERSION} pika-xdebug

COPY docker/web/entrypoint.sh /usr/local/bin/pika-entrypoint
RUN chmod 755 /usr/local/bin/pika-entrypoint

WORKDIR /usr/local/pika
EXPOSE 80
STOPSIGNAL SIGWINCH
ENTRYPOINT ["pika-entrypoint"]
