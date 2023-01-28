FROM php:7.4-cli
RUN apt-get update && apt-get install -y \
	&& apt-get install git unzip wget curl libsodium-dev -y \
    && pecl install inotify mongodb \
    && docker-php-ext-install sodium \
    && echo "extension=mongodb.so" >> /usr/local/etc/php/php.ini \
    && echo "extension=inotify.so" >> /usr/local/etc/php/php.ini \
	&& curl -sS https://getcomposer.org/installer |php \
	&& mv composer.phar /usr/local/bin/composer

WORKDIR /code