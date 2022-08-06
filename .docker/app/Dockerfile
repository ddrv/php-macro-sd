FROM php:8.1.8-alpine3.15

# install composer
COPY composer-install.sh /
RUN sh /composer-install.sh

# install supervisor and php
RUN apk update && \
    apk add --no-cache \
    bash \
    supervisor\
    libpq \
    postgresql-dev \
    sqlite-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
    freetype-dev

RUN docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype && \
    docker-php-ext-install gd sockets pdo pdo_mysql pdo_pgsql pdo_sqlite

# install roadrunner
RUN mkdir /tmp/rr -p && \
    cd /tmp/rr && \
    composer require -q spiral/roadrunner && \
    ./vendor/bin/rr get-binary -n -q -f 2.* && \
    mv ./rr /usr/local/bin/roadrunner && \
    cd / && \
    rm -rf /tmp/rr

# create dir for supervisor logs
RUN mkdir /var/log/supervisor -p

EXPOSE 80

COPY docker-entrypoint.sh /

STOPSIGNAL SIGTERM

VOLUME /opt/app
WORKDIR /opt/app

ENTRYPOINT ["sh", "/docker-entrypoint.sh"]
