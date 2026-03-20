FROM php:8.3-apache-bookworm

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update && apt-get install -y --no-install-recommends \
    libcurl4-openssl-dev \
    libfreetype6-dev \
    libicu-dev \
    libjpeg62-turbo-dev \
    libonig-dev \
    libpng-dev \
    libzip-dev \
    unzip \
    zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        curl \
        exif \
        gd \
        intl \
        mbstring \
        mysqli \
        pdo \
        pdo_mysql \
        zip \
    && a2enmod headers rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh

WORKDIR /var/www/html

COPY . /var/www/html

RUN if [ -f /var/www/html/plugins/ext/file_storage_modules/r2/composer.json ]; then \
        composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --working-dir=/var/www/html/plugins/ext/file_storage_modules/r2; \
    fi

RUN mkdir -p /var/www/html/backups \
    /var/www/html/cache \
    /var/www/html/log \
    /var/www/html/tmp \
    /var/www/html/uploads \
    /var/www/html/uploads/attachments \
    /var/www/html/uploads/attachments_preview \
    /var/www/html/uploads/images \
    /var/www/html/uploads/users \
    && chown -R www-data:www-data /var/www/html

ENTRYPOINT ["docker-entrypoint.sh"]

EXPOSE 80