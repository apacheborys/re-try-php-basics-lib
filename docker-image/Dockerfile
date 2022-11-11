FROM php:7.4-cli
RUN apt-get update && apt-get install -y \
	&& apt-get install git unzip wget curl -y \
	&& curl -sS https://getcomposer.org/installer |php \
	&& mv composer.phar /usr/local/bin/composer

WORKDIR /code