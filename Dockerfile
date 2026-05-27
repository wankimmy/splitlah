# Stage 1: Build
FROM php:8.4-cli AS builder

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

# Stage 2: Production
FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    libxml2-dev \
    netcat-openbsd \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath gd

# Configure php-fpm status/ping endpoints for monitoring
RUN echo 'pm.status_path = /status' >> /usr/local/etc/php-fpm.d/www.conf \
    && echo 'ping.path = /ping' >> /usr/local/etc/php-fpm.d/www.conf

RUN adduser -D -s /bin/sh appuser

COPY --from=builder /var/www/html /var/www/html

# Healthcheck — verifies php-fpm is listening on port 9000
RUN printf '#!/bin/sh\nnc -z 127.0.0.1 9000 || exit 1\n' > /usr/local/bin/php-fpm-healthcheck && chmod +x /usr/local/bin/php-fpm-healthcheck

WORKDIR /var/www/html

RUN chown -R appuser:appuser /var/www/html

USER appuser

ENV APP_ENV=production APP_DEBUG=false

EXPOSE 9000

CMD ["php-fpm"]