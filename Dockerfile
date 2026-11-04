# ===============================================
# Stage 1: Builder
# Installs Composer dependencies and builds npm assets
# ===============================================
FROM composer:2.8 AS builder

WORKDIR /app

# Install Node.js for asset building
RUN apk add --no-cache nodejs npm

# Install PHP extensions required for Composer dependencies
RUN apk add --no-cache icu-dev \
    && docker-php-ext-install intl \
    && rm -rf /var/cache/apk/*

# Copy Composer files
COPY composer.json composer.lock ./

# Install production dependencies
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    --no-ansi

# Copy Node dependency manifests first for stable npm cache layer
COPY package.json package-lock.json ./

# Install frontend dependencies deterministically
RUN npm ci

# Copy application files
COPY . .

# Build frontend assets
RUN npm run build

# Clear bootstrap cache and drop build-only node_modules from the runtime image
RUN rm -rf bootstrap/cache/*.php node_modules

# ===============================================
# Stage 2: Production (pfnapp PHP base: Alpine, non-root UID 10001, port 8080)
# ===============================================
FROM ghcr.io/pfnapp/base/languages/php:8.5

USER root

# pfnapp base ships pdo_mysql only; this app runs on PostgreSQL
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pgsql pdo_pgsql \
    && rm /usr/local/bin/install-php-extensions

WORKDIR /var/www/html

# Copy built application from builder
COPY --from=builder --chown=10001:10001 /app /var/www/html

# Base supervisord.conf has no include dir, so append queue + scheduler programs
COPY docker/supervisor/app-services.conf /tmp/app-services.conf
RUN cat /tmp/app-services.conf >> /etc/supervisor/supervisord.conf \
    && rm /tmp/app-services.conf

COPY --chown=10001:10001 docker/nginx/dynamic-routes.conf /etc/nginx/conf.d/custom/routes-laravel.conf
COPY --chmod=755 docker/app-bootstrap.sh /usr/local/bin/app-bootstrap.sh

USER 10001:10001

# Set permissions for storage and cache
RUN mkdir -p bootstrap/cache \
        storage/framework/views \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/logs \
        storage/app/public \
    && chmod -R 775 storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    PHP_MEMORY_LIMIT=256M \
    PHP_UPLOAD_MAX_FILESIZE=64M

EXPOSE 8080

# Runs migrations/caches, then hands off to the base entrypoint (supervisord)
ENTRYPOINT ["/usr/local/bin/app-bootstrap.sh"]

# OCI image description
LABEL org.opencontainers.image.description="${DESCRIPTION:-Laravel Marketing Mail Application}"
