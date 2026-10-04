FROM node:24-bookworm-slim AS assets
WORKDIR /app
RUN npm install --global pnpm@9.15.4
COPY . .
RUN pnpm install --frozen-lockfile && pnpm run build

FROM php:8.3-apache-bookworm AS runtime
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev libzip-dev libonig-dev libxml2-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 pdo_pgsql mbstring bcmath zip gd opcache pcntl \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY . .
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache \
    && composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache
COPY --from=assets /app/public/build public/build
COPY deploy/apache.conf /etc/apache2/sites-available/000-default.conf
COPY deploy/start.sh /usr/local/bin/erp-start
RUN sed -i 's/\r$//' /usr/local/bin/erp-start && chmod +x /usr/local/bin/erp-start
ENV APP_ENV=production APP_DEBUG=false PORT=10000
EXPOSE 10000
CMD ["/usr/local/bin/erp-start"]
