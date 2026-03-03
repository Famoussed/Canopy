# docker/Dockerfile
FROM php:8.3-fpm-alpine

# System dependencies
RUN apk add --no-cache \
    linux-headers \
    postgresql-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    nodejs \
    npm \
    supervisor

# PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    zip \
    pcntl \
    bcmath \
    opcache

# Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP config
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html

# App dosyalarını kopyala
COPY . .

# Composer install (production)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# NPM build
RUN npm ci && npm run build

# Permissions
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
