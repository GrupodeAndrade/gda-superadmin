FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libonig-dev \
        libcurl4-openssl-dev \
        libzip-dev \
    && docker-php-ext-install -j"$(nproc)" \
        mbstring \
        curl \
        pdo_mysql \
        zip \
    && a2enmod rewrite \
    && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . /var/www/html

RUN mkdir -p \
        /var/www/html/config \
        /var/www/html/storage/backups \
        /var/www/html/storage/cache \
        /var/www/html/storage/logs \
        /var/www/html/storage/temp \
        /var/www/html/storage/updates \
        /var/www/html/storage/uploads \
    && chown www-data:www-data /var/www/html/config \
    && chmod 0775 /var/www/html/config \
    && chown -R www-data:www-data /var/www/html/storage \
    && find /var/www/html/storage -type d -exec chmod 0775 {} + \
    && find /var/www/html/storage -type f -exec chmod 0664 {} +

EXPOSE 80
