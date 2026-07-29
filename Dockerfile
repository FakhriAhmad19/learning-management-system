# =========================================================================
# Stage 1: Build aset frontend (Tailwind + Vite) untuk produksi
# =========================================================================
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# =========================================================================
# Stage 2: Aplikasi PHP 8.4 (Laravel 13 + Filament — sesuai PRD: PHP 8.4+)
# =========================================================================
FROM php:8.4-cli

# Dependensi sistem yang dibutuhkan ekstensi PHP & tooling
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        zip \
        curl \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libzip-dev \
        libonig-dev \
        libicu-dev \
        default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Ekstensi PHP yang dipakai Laravel + Filament (MySQL, gambar, format angka, dll)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        bcmath \
        gd \
        zip \
        intl \
        exif \
        pcntl

# Composer (disalin dari image resmi)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Aset hasil build Vite dari stage "assets" (agar image mandiri untuk produksi)
COPY --from=assets /app/public/build ./public/build

# Skrip entrypoint: tunggu DB, migrasi, seed, lalu jalankan server
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["entrypoint.sh"]
