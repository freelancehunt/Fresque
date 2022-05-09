FROM php:8.1-fpm

RUN apt-get update && apt-get install -y procps

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions redis posix pcntl zip
