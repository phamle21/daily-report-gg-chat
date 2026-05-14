FROM php:8.2-fpm

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    libicu-dev \
    tzdata \
    && docker-php-ext-install pdo mbstring xml curl intl gd

# Set timezone
RUN ln -snf /usr/share/zoneinfo/Asia/Ho_Chi_Minh /etc/localtime && echo "Asia/Ho_Chi_Minh" > /etc/timezone

# Configure PHP
RUN echo "date.timezone = Asia/Ho_Chi_Minh" > /usr/local/etc/php/conf.d/tzone.ini
RUN echo "upload_max_filesize = 10M" >> /usr/local/etc/php/conf.d/uploads.ini
RUN echo "post_max_size = 10M" >> /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /app

COPY --chown=www-data:www-data . /app

# Create necessary directories with proper permissions
RUN mkdir -p /app/public/logs /app/public/history && \
    chown -R www-data:www-data /app/public/logs /app/public/history && \
    chmod -R 775 /app/public/logs /app/public/history

# Expose port
EXPOSE 9000

# Healthcheck
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php-fpm --test || exit 1
