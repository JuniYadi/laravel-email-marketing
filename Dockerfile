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

# Install supervisord and netcat for health checks
RUN apt-get update && apt-get install -y supervisor netcat-openbsd && rm -rf /var/lib/apt/lists/*

# Create supervisor log directory
RUN mkdir -p /var/log/supervisor

# Copy built application from builder
COPY --from=builder --chown=www-data:www-data /app /var/www/html

# Set permissions for storage and cache
RUN chmod -R 775 storage bootstrap/cache || true

# Copy nginx configuration
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Copy supervisord configuration
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy entrypoint script
COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Entrypoint handles setup, migrations
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

# Start supervisord (manages PHP-FPM, Nginx, Scheduler, Queue)
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# OCI image description
LABEL org.opencontainers.image.description="${DESCRIPTION:-Laravel Marketing Mail Application}"
