FROM php:8.4-fpm-alpine

LABEL maintainer="EU Pay <dev@eupay.eu>"
LABEL description="EU Pay Backend — Symfony 8 / PHP 8.4"

# ── System deps ──────────────────────────────────────
RUN apk add --no-cache \
    icu-dev \
    libpq-dev \
    libsodium-dev \
    libzip-dev \
    oniguruma-dev \
    linux-headers \
    $PHPIZE_DEPS

# ── PHP extensions ───────────────────────────────────
RUN docker-php-ext-install -j$(nproc) \
    intl \
    mbstring \
    opcache \
    pdo_pgsql \
    sodium \
    zip \
    pcntl

# ── OPcache production settings ─────────────────────
RUN { \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.enable_cli=1'; \
    echo 'opcache.jit=1255'; \
    echo 'opcache.jit_buffer_size=128M'; \
} > /usr/local/etc/php/conf.d/opcache.ini

# ── PHP config ───────────────────────────────────────
RUN { \
    echo 'memory_limit=256M'; \
    echo 'upload_max_filesize=10M'; \
    echo 'post_max_size=12M'; \
    echo 'max_execution_time=30'; \
    echo 'date.timezone=Europe/Berlin'; \
    echo 'expose_php=Off'; \
} > /usr/local/etc/php/conf.d/eupay.ini

# ── Composer ─────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── App ──────────────────────────────────────────────
WORKDIR /app

COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY backend/ .
RUN composer dump-autoload --optimize --classmap-authoritative

# ── Runtime user ─────────────────────────────────────
RUN addgroup -g 1000 eupay && adduser -u 1000 -G eupay -s /bin/sh -D eupay \
    && chown -R eupay:eupay /app/var || true
USER eupay

EXPOSE 9000
CMD ["php-fpm"]
