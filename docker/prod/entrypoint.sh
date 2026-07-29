#!/bin/sh
set -e

cd /var/www/html

echo "[prod] Menyiapkan aplikasi..."

# -------------------------------------------------------------------------
# 1. Pemeriksaan wajib — gagal cepat dan jelas, jangan jalan setengah benar
# -------------------------------------------------------------------------
if [ -z "${APP_KEY}" ]; then
    echo "[prod] GAGAL: APP_KEY belum diisi." >&2
    echo "[prod] APP_KEY TIDAK dibuat otomatis di sini. Membuatnya ulang tiap" >&2
    echo "[prod] deploy akan membatalkan seluruh sesi dan merusak data terenkripsi." >&2
    echo "[prod] Buat sekali saja: php artisan key:generate --show" >&2
    echo "[prod] lalu simpan sebagai variabel lingkungan APP_KEY." >&2
    exit 1
fi

if [ "${APP_DEBUG}" = "true" ]; then
    echo "[prod] GAGAL: APP_DEBUG=true di produksi." >&2
    echo "[prod] Mode ini membocorkan stack trace beserta isi variabel" >&2
    echo "[prod] lingkungan — termasuk kredensial database — ke pengunjung." >&2
    exit 1
fi

# -------------------------------------------------------------------------
# 2. Struktur direktori storage
#    storage/ adalah volume, jadi isinya bisa kosong saat pertama dibuat.
# -------------------------------------------------------------------------
mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

# Symlink public/storage -> storage/app/public (berkas unggahan)
php artisan storage:link --force || true

# -------------------------------------------------------------------------
# 3. Tunggu database siap
# -------------------------------------------------------------------------
echo "[prod] Menunggu database di ${DB_HOST}:${DB_PORT}..."
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
echo "[prod] Database siap."

# -------------------------------------------------------------------------
# 4. Migrasi — TANPA seeder.
#    Seeder membuat akun demo (admin@lms.com / password123) yang
#    passwordnya tertulis publik di README. Tidak boleh ada di produksi.
# -------------------------------------------------------------------------
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "[prod] Menjalankan migrasi..."
    php artisan migrate --force
else
    echo "[prod] RUN_MIGRATIONS=false — migrasi dilewati."
fi

# -------------------------------------------------------------------------
# 5. Cache konfigurasi, rute, dan view
# -------------------------------------------------------------------------
echo "[prod] Membangun cache..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "[prod] Siap. Menjalankan ${*}"
exec "$@"
