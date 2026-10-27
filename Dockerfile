# syntax=docker/dockerfile:1.7

FROM php:8.5-apache

# Composer is only used during the image build.
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    APACHE_DOCUMENT_ROOT=/var/www/html/public \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libicu-dev \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        mbstring \
        opcache \
        pdo_mysql \
        xml \
        zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Install dependencies first so this layer is cached when only application files change.
COPY composer.json composer.lock symfony.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --no-autoloader \
    --prefer-dist

COPY . .

# Build the production autoloader and compile AssetMapper assets into public/assets.
RUN mkdir -p var/cache var/log var/sessions public/assets \
    && composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction \
    && APP_ENV=prod APP_DEBUG=0 APP_SECRET=build-only DEFAULT_URI=http://localhost \
        php bin/console importmap:install --no-interaction \
    && APP_ENV=prod APP_DEBUG=0 APP_SECRET=build-only DEFAULT_URI=http://localhost \
        php bin/console asset-map:compile --no-interaction \
    && rm -rf var/cache/* var/log/* \
    && chown -R www-data:www-data var public/assets

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/99-budgett.ini
COPY docker/entrypoint.sh /usr/local/bin/budgett-entrypoint
RUN chmod +x /usr/local/bin/budgett-entrypoint

EXPOSE 80

ENTRYPOINT ["budgett-entrypoint"]
CMD ["apache2-foreground"]
