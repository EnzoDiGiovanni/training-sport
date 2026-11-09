# Image de production : FrankenPHP (PHP + serveur Caddy dans un seul processus, pas de couple Nginx/PHP-FPM à superviser).
# Coolify termine le TLS en amont (Traefik) ; le conteneur écoute en HTTP sur le port 80.

# 1) Assets Vite
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund
COPY vite.config.ts tsconfig.json ./
COPY resources ./resources
RUN npm run build

# 2) Dépendances PHP
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative --no-scripts

# 3) Exécution
FROM dunglas/frankenphp:1-php8.4-bookworm
RUN install-php-extensions pdo_mysql zip opcache pcntl \
    && apt-get update && apt-get install -y --no-install-recommends default-mysql-client curl \
    && rm -rf /var/lib/apt/lists/*
ENV SERVER_NAME=:80 APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr
WORKDIR /app
COPY --from=vendor /app /app
COPY --from=assets /app/public/build /app/public/build
COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-app.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint \
    && php artisan package:discover && rm -rf docs tests node_modules .env \
    && chown -R www-data:www-data storage bootstrap/cache
# storage/ est monté sur un volume persistant (images importées, logs, sessions fichiers).
VOLUME /app/storage
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s CMD curl -fsS http://127.0.0.1/up || exit 1
ENTRYPOINT ["entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
