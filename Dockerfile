FROM php:7.2.0-fpm-alpine as php

# persistent / runtime deps
RUN apk add --no-cache \
        acl \
        fcgi \
        file \
        gettext \
        git \
    ;

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /usr/src

# prevent the reinstallation of vendors at every changes in the source code
COPY composer.json ./
RUN set -eux; \
    composer install; \
    composer clear-cache

# copy only specifically what we need
COPY src src/
COPY tests tests/

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]
