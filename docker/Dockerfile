# syntax=docker/dockerfile:1

###############################################################################
# Stage 1: build front-end assets (Vite + Tailwind CSS)
###############################################################################
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./

RUN npm run build

###############################################################################
# Stage 2: PHP-FPM application image
###############################################################################
FROM php:8.3-fpm-alpine AS app

RUN apk add --no-cache \
        bash \
        curl \
        git \
        icu-dev \
        libzip-dev \
        postgresql-dev \
        su-exec \
        $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS \
    && rm -rf /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-stats-radar.ini

WORKDIR /var/www/html

# Install PHP dependencies first so this layer is cached when only app code changes.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-scripts \
        --no-autoloader \
        --prefer-dist

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --no-dev --optimize --no-interaction \
    && mkdir -p \
        bootstrap/cache \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

###############################################################################
# Stage 3: Nginx web server (serves the same public assets)
###############################################################################
FROM nginx:1.27-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public

EXPOSE 80
