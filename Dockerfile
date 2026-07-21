FROM composer:2 AS composer

FROM php:8.3-apache

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf \
    && a2enmod rewrite \
    && mkdir -p storage/cache storage/logs storage/uploads \
    && chown -R www-data:www-data storage/cache storage/logs storage/uploads \
    && chmod -R 775 storage/cache storage/logs storage/uploads

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php -r "exit((bool) @file_get_contents('http://localhost/api/v1/health') ? 0 : 1);"
