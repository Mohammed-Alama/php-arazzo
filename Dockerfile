# ============================================================================
# Stage 1: Build PHP extensions
# ============================================================================
FROM php:8.4-cli-alpine AS builder

RUN apk add --no-cache \
    sqlite-dev \
    oniguruma-dev \
    libxml2-dev \
    $PHPIZE_DEPS \
    && docker-php-ext-install pdo_sqlite mbstring xml \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/pear

# ============================================================================
# Stage 2: Final minimal image
# ============================================================================
FROM php:8.4-cli-alpine

# Copy compiled extensions from builder
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Install runtime dependencies (no composer from apk - it pulls php85)
RUN apk add --no-cache \
    bash \
    git \
    nodejs \
    npm \
    sqlite-libs \
    && rm -rf /var/cache/apk/* /tmp/* /usr/share/doc /usr/share/man

# Install composer manually (avoids php85 dependency conflict)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
