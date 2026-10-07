# Laravel API image for Render (Render has no native PHP runtime)
FROM php:8.2-apache

# System packages composer needs + MySQL driver
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip ca-certificates \
    && docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Serve Laravel's public/ folder
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies first so this layer is cached between deploys
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x docker/start.sh

CMD ["docker/start.sh"]
