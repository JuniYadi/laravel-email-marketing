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

# Copy application files
COPY . .

# Install npm dependencies and build assets
RUN npm install && npm run build

# Create .env from .env.example and generate APP key
RUN if [ -z "$APP_KEY" ]; then \
    cp .env.example .env \
    && php -r "echo 'APP_KEY=base64:' . base64_encode(random_bytes(32)) . PHP_EOL;" > /tmp/key.txt \
    && grep -v "^APP_KEY=" .env > /tmp/.env.tmp \
    && cat /tmp/key.txt >> /tmp/.env.tmp \
    && mv /tmp/.env.tmp .env; \
    fi

# Clear bootstrap cache
RUN rm -rf bootstrap/cache/*.php

# ===============================================
# Stage 2: Production (using php-base)
# ===============================================
FROM ghcr.io/juniyadi/php-base:8.5

# Enable required extensions via environment variables
ENV PHP_EXT_bcmath=1
ENV PHP_EXT_pgsql=1
ENV PHP_EXT_pdo_pgsql=1

# Trust Cloudflare proxy - enables real IP forwarding in nginx
ENV NGINX_TRUST_CLOUDFLARE=1

# Install app runtime dependencies
RUN apt-get update && apt-get install -y cron netcat-openbsd && rm -rf /var/lib/apt/lists/*

# Copy built application from builder
COPY --from=builder --chown=www-data:www-data /app /var/www/html

# Set permissions for storage and cache
RUN chmod -R 775 storage bootstrap/cache || true

# Copy app-specific Supervisor programs (php-base keeps core supervisord config)
COPY docker/supervisor/app-services.conf /etc/supervisor.d/app-services.conf

# Copy crontab file
COPY docker/crontab /etc/cron.d/laravel
RUN chmod 0644 /etc/cron.d/laravel && crontab /etc/cron.d/laravel

# Copy app bootstrap hook that runs before php-base start.sh
COPY docker/app-bootstrap.sh /usr/local/bin/app-bootstrap.sh
RUN chmod +x /usr/local/bin/app-bootstrap.sh

# Keep php-base entrypoint for dynamic secure runtime config.
# Run app bootstrap, then launch php-base process supervisor loop.
CMD ["sh", "-lc", "/usr/local/bin/app-bootstrap.sh && exec /usr/local/bin/start.sh"]

# OCI image description
LABEL org.opencontainers.image.description="${DESCRIPTION:-Laravel Marketing Mail Application}"
