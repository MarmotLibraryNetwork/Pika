#!/usr/bin/env bash

echo Stoping contiouns indexing...
kill $(ps aux | grep [c]ontinuous_reindex | awk '{print $2}')

echo Installing yum-utils...
yum install yum-utils \
|| { printf "%b" "Failed to install yum-utils. Exiting.\n" ; exit 1 ; }

echo Stoping Apache...
apachectl stop \
|| { printf "%b" "Failed to stop Apache. Please restart Apache after script exits.\nContinuing... \n" ; }

echo Moving PHP 5.x Apache conf file...
mv /etc/httpd/conf.modules.d/10-php55-php.conf /etc/httpd/conf.modules.d/10-php55-php.NOconf

echo Enabling Remi PHP 7.4 repo...
yum-config-manager --enable remi-php74 \
|| { printf "%b" "Failed to enable Remi PHP 7.4 repo. Exiting.\n" ; exit 1 ; }

echo Updating PHP packages...
yum update \
&& yum install -y php74 php \
php74-php-pecl-memcached php-pecl-memcached \
php74-php-pecl-memcache php-pecl-memcache \
php74-php-pecl-mysql php-mysql \
php74-php-mysqlnd php-mysqlnd \
php74-php-pgsql php-pgsql \
php74-php-xml php-xml \
php74-php-soap php-soap \
|| { printf "%b" "Failed to install PHP 7 packages. Exiting.\n" ; exit 1 ; }
echo
echo
echo PHP 7.4 packages installed.
echo 
echo Before continuing, please check that the PHP version displayed below is 7.4.x
echo If the version is incorrect please change the path in the opened file.
php -v
echo
read -p "Is the version correct? [y\n]" YN
case "$YN" in
	[yY] ) printf "%b" "Version is good. Continuing...\n" 
		;;
	[nN] ) eval "vi /etc/profile.d/path.sh ; source /etc/profile.d/path.sh"
		;;
esac
echo

echo Installing Composer packages...
cd /usr/local/vufind-plus/install \
&& composer update \
|| { printf "%b" "Failed to install Composer packages. Exiting.\n" ; exit 1 ; }

echo Copying DataObject to vendor directory
cp -r /usr/local/vufind-plus/install/PEAR/DB/* /usr/share/composer/vendor/pear/db/DB \
|| { printf "%b" "Failed to copy DataObject. Please check that directory exists.\nExiting.\n" ; exit 1 ; }
cd /usr/share/composer/vendor/pear/db/DB
chmod -R 655 DataObject
chmod 644 DataObject.php


echo PHP 7 upgrade complete!
