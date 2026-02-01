FROM hyperf/hyperf:8.1-alpine-v3.18-swoole

LABEL maintainer="TecnoFit" \
      version="1.0" \
      description="Hyperf PHP 3.1 with Swoole"

# Set working directory
WORKDIR /data/project

# Install additional dependencies if needed
RUN apk add --no-cache \
    git \
    curl \
    zip \
    unzip \
    bash

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Environment variables are passed via docker-compose from .env file:
# - APP_ENV
# - SCAN_CACHEABLE
# - DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
# - MAIL_HOST, MAIL_PORT

# Expose Hyperf default port
EXPOSE 9501

# Start Hyperf server
CMD ["php", "bin/hyperf.php", "start"]
