FROM node:24-bookworm-slim AS frontend
WORKDIR /app
COPY frontend/package*.json ./
RUN npm ci --ignore-scripts --no-audit --no-fund
COPY frontend/ ./
ENV VITE_API_URL=/api VITE_DEMO=true
RUN npm run build

FROM php:8.5-apache
RUN apt-get update && apt-get install -y --no-install-recommends \
    libicu-dev libzip-dev libonig-dev libssl-dev pkg-config unzip ca-certificates \
    && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install -j2 intl mbstring zip
RUN pecl install mongodb-2.5.3 && docker-php-ext-enable mongodb && a2enmod rewrite headers
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY backend/ ./
RUN mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache \
    && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress --no-scripts \
    && composer check-platform-reqs --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache
COPY --from=frontend /app/dist/ ./public/
COPY docker/publicacion/public.htaccess ./public/.htaccess
COPY docker/publicacion/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/publicacion/php.ini /usr/local/etc/php/conf.d/erp.ini
COPY docker/publicacion/iniciar.sh /usr/local/bin/iniciar-erp
RUN chmod +x /usr/local/bin/iniciar-erp
ENV APP_ENV=production APP_DEBUG=false APP_LOCALE=es APP_FALLBACK_LOCALE=en \
    DB_CONNECTION=mongodb DB_DATABASE=libreria_demo CACHE_STORE=mongodb \
    SESSION_DRIVER=file QUEUE_CONNECTION=sync LOG_CHANNEL=stderr \
    JWT_COOKIE_SECURE=true JWT_COOKIE_NAME=libreria_publica_refresh PORT=10000
EXPOSE 10000
CMD ["iniciar-erp"]
