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

# Copy composer files first for better layer caching
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy application code
COPY app/ ./app/
COPY bin/ ./bin/
COPY config/ ./config/

# Generate optimized autoloader
RUN composer dump-autoload --optimize --no-dev

# Copy and set up entrypoint script (convert CRLF to LF for Windows compatibility)
COPY docker/hyperf/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

# Expose Hyperf default port
EXPOSE 9501

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php", "bin/hyperf.php", "start"]
