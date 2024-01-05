FROM php:8.2-cli-alpine3.18

RUN apk add --no-cache \
        libzip-dev \
        openssl-dev \
        linux-headers

RUN docker-php-ext-install -j$(nproc) \
        zip \
        bcmath

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer

RUN apk add --no-cache --virtual .phpize_deps $PHPIZE_DEPS && \
    pecl install xdebug 3 && \
    docker-php-ext-enable xdebug && \
    rm -rf /usr/share/php7 && \
    rm -rf /tmp/pear && \
    apk del .phpize_deps

ENV PATH /var/app/bin:/var/app/vendor/bin:$PATH

WORKDIR /var/app