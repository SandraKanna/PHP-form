FROM php:8.3-apache

# enable mod_rewrite
RUN a2enmod rewrite

# dev settings with logs
RUN { \
    echo 'display_errors=On'; \
    echo 'error_reporting=E_ALL'; \
} > /usr/local/etc/php/conf.d/dev.ini

# curl (for healthcheck) + SQLite extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_sqlite

WORKDIR /var/www/html