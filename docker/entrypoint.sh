#!/usr/bin/env bash
set -e

cd /var/www/html

# 1. Pasang dependensi PHP bila folder vendor belum ada
if [ ! -d vendor ]; then
    echo "[entrypoint] Menginstal dependensi Composer..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# 2. Pastikan berkas .env tersedia
if [ ! -f .env ]; then
    echo "[entrypoint] Menyalin .env.example -> .env"
    cp .env.example .env
fi

# 3. Generate APP_KEY bila belum ada
if ! grep -q "^APP_KEY=base64:" .env; then
    echo "[entrypoint] Membuat APP_KEY..."
    php artisan key:generate --force
fi

# 4. Tunggu MySQL siap menerima koneksi (cek via PDO agar bebas dari isu SSL client)
echo "[entrypoint] Menunggu MySQL di ${DB_HOST}:${DB_PORT}..."
until php -r '
    try {
        new PDO(
            "mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT"),
            getenv("DB_USERNAME"),
            getenv("DB_PASSWORD")
        );
        exit(0);
    } catch (Throwable $e) {
        exit(1);
    }
'; do
    sleep 2
done
echo "[entrypoint] MySQL siap."

# 5. Jalankan migrasi + seeder (seeder bersifat idempotent, aman diulang)
php artisan migrate --seed --force

# 6. Symlink storage agar berkas unggahan (thumbnail/lampiran) bisa diakses publik
php artisan storage:link || true

# 7. Bersihkan cache konfigurasi/route
php artisan config:clear
php artisan route:clear

# 8. Jalankan development server Laravel
echo "[entrypoint] Menjalankan server di http://0.0.0.0:8000"
exec php artisan serve --host=0.0.0.0 --port=8000
