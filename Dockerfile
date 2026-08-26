FROM php:8.2-fpm AS php

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    libicu-dev \
    tzdata \
    && docker-php-ext-install pdo mbstring xml curl intl gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Set timezone
RUN ln -snf /usr/share/zoneinfo/Asia/Ho_Chi_Minh /etc/localtime && echo "Asia/Ho_Chi_Minh" > /etc/timezone

# Configure PHP
RUN printf '%s\n' \
    'date.timezone = Asia/Ho_Chi_Minh' \
    'upload_max_filesize = 10M' \
    'post_max_size = 10M' \
    'session.cookie_httponly = 1' \
    'session.cookie_samesite = Lax' \
    > /usr/local/etc/php/conf.d/daily-report.ini

WORKDIR /var/www/html

COPY --chown=www-data:www-data public/ /var/www/html/

# Create necessary directories with proper permissions
RUN mkdir -p /var/www/html/logs /var/www/html/history && \
    chown -R www-data:www-data /var/www/html/logs /var/www/html/history && \
    chmod -R 775 /var/www/html/logs /var/www/html/history

EXPOSE 9000

# Healthcheck
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php-fpm --test || exit 1

# Nginx image contains its own copy of static files. No host bind mount is required.
FROM nginx:alpine AS nginx

COPY public/ /var/www/html/
COPY nginx/default.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD wget -q -O /dev/null http://127.0.0.1/ || exit 1
