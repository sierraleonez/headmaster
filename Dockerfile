# syntax=docker/dockerfile:1
#
# HeadMaster ships as three images built from one file:
#   vendor  - composer dependencies
#   assets  - the Vite build (needs PHP as well as Node: the Wayfinder plugin
#             runs `artisan wayfinder:generate` to type the route helpers)
#   app     - php-fpm, serving the application and running the queue worker
#   web     - nginx, serving public/ and passing PHP to app
#
# Debian rather than Alpine throughout: the Tailwind and Rollup native builds
# are far better behaved against glibc.

##############################  composer  ##############################
FROM php:8.4-cli AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libzip-dev \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql zip \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Dependencies first, so a source-only change does not re-resolve them.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader \
        --prefer-dist --no-interaction --no-progress

COPY . .

# Artisan needs an environment to boot; the real one arrives at runtime.
RUN cp .env.example .env \
 && composer dump-autoload --optimize --no-dev --classmap-authoritative \
 && php artisan package:discover --ansi \
 && rm .env

##############################  vite build  ##############################
FROM php:8.4-cli AS assets

COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

WORKDIR /app

COPY --from=vendor /app/vendor ./vendor
COPY --from=vendor /app/bootstrap/cache ./bootstrap/cache
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .
RUN cp .env.example .env \
 && php artisan wayfinder:generate --with-form \
 && npm run build \
 && rm .env

##############################  application  ##############################
FROM php:8.4-fpm AS app

RUN apt-get update \
 && apt-get install -y --no-install-recommends libzip-dev \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql zip bcmath pcntl opcache \
 && rm -rf /var/lib/apt/lists/*

# Queue workers and long requests want a compiled, warm opcache.
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=0'; \
        echo 'opcache.memory_consumption=192'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/opcache.ini \
 && { \
        echo 'memory_limit=512M'; \
        echo 'upload_max_filesize=20M'; \
        echo 'post_max_size=20M'; \
        echo 'expose_php=Off'; \
    } > /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=vendor --chown=www-data:www-data /app/bootstrap/cache ./bootstrap/cache
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint \
 && chown -R www-data:www-data storage bootstrap/cache

USER www-data

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

##############################  web  ##############################
FROM nginx:1.27-alpine AS web

COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
# Static assets are served by nginx directly, so it needs public/ too.
COPY --from=assets /app/public /var/www/html/public
