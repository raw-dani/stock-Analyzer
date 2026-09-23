# Dockerfile — US Stock Volume Analyzer (Modul 15.4)
# PHP-FPM image dengan ekstensi yang dibutuhkan (pdo_mysql, sqlite, mbstring, intl).
# Digunakan oleh deploy/docker-compose.yml (service `app`).

FROM php:8.1-fpm-alpine

# Ekstensi sistem
RUN apk add --no-cache \
        $PHPIZE_DEPS \
        libzip-dev \
        libpng-dev \
        oniguruma-dev \
        icu-dev \
        nginx \
        supervisor \
        bash \
    && docker-php-ext-install \
        pdo_mysql \
        pdo_sqlite \
        mysqli \
        mbstring \
        intl \
        zip \
        gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

WORKDIR /var/www/html

# Salin source ke container (project di-mount volume di docker-compose)
COPY . /var/www/html
RUN chmod -R a+w /var/www/html/runtime /var/www/html/web/assets /var/www/html/data

# WWW data dir untuk SQLite (dev) & file csv
RUN mkdir -p /var/www/html/data && chown -R www-data:www-data /var/www/html

# PHP production preset
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Supervisor: jalankan FPM + cron + (opsional) queue worker Redis
COPY deploy/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY deploy/crontab.example /etc/cron.d/stock-analyzer
RUN chmod 0644 /etc/cron.d/stock-analyzer

EXPOSE 9000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]