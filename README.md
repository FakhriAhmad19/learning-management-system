<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/FakhriAhmad19/learning-management-system/actions/workflows/tests.yml"><img src="https://github.com/FakhriAhmad19/learning-management-system/actions/workflows/tests.yml/badge.svg" alt="Status Test"></a>
</p>

<p align="center">
Learning Management System berbasis <strong>Laravel 13</strong> + <strong>Filament 3</strong>.
</p>

## Menjalankan dengan Docker (MySQL)

Proyek ini dikonfigurasi untuk berjalan di Docker dengan database MySQL 8.

```bash
# Bangun image & jalankan seluruh service (app + MySQL + Adminer)
docker compose up -d --build
```

Layanan yang tersedia:

| Layanan | URL / Port | Keterangan |
| :--- | :--- | :--- |
| Aplikasi (Laravel) | http://localhost:8090 | Landing page & katalog kursus |
| Panel Admin (Filament) | http://localhost:8090/admin | Login: `admin@lms.com` / `password123` |
| Adminer (DB UI) | http://localhost:8081 | Server: `db`, user: `lms_user`, pass: `secret`, DB: `learning_system` |
| Mailpit (Email) | http://localhost:8025 | Menangkap semua email keluar (verifikasi, reset password, notifikasi) |

Selain itu berjalan service `queue` (tanpa port) berisi `php artisan queue:work`. Service ini **wajib hidup** karena notifikasi email diantrekan (`QUEUE_CONNECTION=database`); tanpa worker, email menumpuk di tabel `jobs` dan tidak pernah terkirim. Cek dengan `docker compose logs queue`.

> MySQL tidak dipublish ke host (menghindari bentrok port dengan MySQL lokal / proyek Docker lain). Akses database lewat Adminer atau `docker compose exec db mysql -ulms_user -psecret learning_system`.

Migrasi & seeder otomatis dijalankan saat container `app` pertama kali start.
Perintah artisan dapat dijalankan via: `docker compose exec app php artisan <perintah>`.

**Aset frontend (Tailwind + Vite)** dikompilasi otomatis di dalam image saat `docker build` (stage Node), jadi tidak perlu memasang Node di host. Untuk pengembangan lokal aset di luar Docker: `npm install && npm run build` (atau `npm run dev` untuk hot-reload).

> **Penting saat mengubah Blade/CSS:** `public/build` dipasang sebagai anonymous volume, sehingga `docker compose up -d --build` saja akan tetap menyajikan CSS lama dan class Tailwind baru diam-diam hilang. Selalu gunakan:
>
> ```bash
> docker compose up -d --build --renew-anon-volumes
> ```

### Email / SMTP

Secara default email dikirim via SMTP ke **Mailpit** (`MAIL_HOST=mailpit`, `MAIL_PORT=1025`) dan bisa dilihat di http://localhost:8025 — cocok untuk pengembangan tanpa server email nyata.

Untuk **produksi**, ganti kredensial SMTP di `.env` dengan penyedia sungguhan (Mailgun, Amazon SES, Postmark, SendGrid, dll.), contoh:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@domainkamu.com
MAIL_PASSWORD=rahasia
MAIL_SCHEME=tls
MAIL_FROM_ADDRESS="no-reply@domainkamu.com"
```

### Menjalankan test

Test berjalan di **MySQL** (mesin yang sama dengan produksi), pada database
terpisah `learning_system_test` yang dibuat otomatis. Jalankan dari dalam
container, karena MySQL tidak dipublikasikan ke host:

```bash
docker compose exec app php artisan test
```

Test dan pemeriksaan gaya kode juga berjalan otomatis di GitHub Actions pada
setiap push dan pull request ke `main` — lihat
[.github/workflows/tests.yml](.github/workflows/tests.yml). Nama database pada
service MySQL di CI harus sama dengan yang dikunci pada koneksi
`mysql_testing` di `config/database.php`.

---

## Menjalankan di Produksi

Susunan produksi terpisah penuh dari pengembangan: `Dockerfile.prod` +
`docker-compose.prod.yml` (nama proyek `learning-system-prod`, sehingga
perintah di satu stack tidak pernah menyentuh stack lainnya).

Perbedaan pokok dari setup pengembangan:

| Aspek | Pengembangan | Produksi |
| :--- | :--- | :--- |
| Web server | `php artisan serve` (satu proses) | nginx + php-fpm |
| Kode | bind mount dari host | dibekukan ke dalam image |
| Dependensi | lengkap | `--no-dev`, autoloader authoritative |
| Seeder | dijalankan tiap start | **tidak pernah dijalankan** |
| OPcache | mati | aktif, tanpa cek timestamp |
| Cache Laravel | mati | config, route, view, event |
| Berkas unggahan | ikut direktori proyek | volume `storage` |
| Log | berkas di dalam container | stderr (`docker logs`) |

### Langkah

```bash
cp .env.production.example .env.production
```

Isi setiap nilai bertanda `GANTI`. Untuk `APP_KEY`, buat **sekali saja** lalu
simpan permanen — membuatnya ulang tiap deploy akan membatalkan semua sesi dan
merusak data terenkripsi termasuk secret 2FA:

```bash
docker compose exec app php artisan key:generate --show
```

Lalu jalankan:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build
```

> `--env-file` **wajib**. `env_file:` di compose hanya mengisi variabel di dalam
> container, sedangkan interpolasi `${...}` pada berkas compose dibaca dari
> `--env-file`. Tanpa itu password database akan kosong.

Aplikasi mendengarkan di `127.0.0.1:8091` saja — **letakkan reverse proxy
(Caddy/Traefik/nginx) di depannya untuk menangani HTTPS**. Jangan buka port itu
langsung ke internet.

### Membuat akun admin pertama

Seeder tidak dijalankan di produksi, jadi database mulai kosong. Daftarkan diri
lewat halaman registrasi, lalu berikan peran Admin:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml exec app \
  php artisan tinker --execute="\App\Models\User::where('email','emailkamu@contoh.com')->first()->assignRole('Admin');"
```

### Yang masih perlu disiapkan sendiri

Paket ini menutup pemblokir teknis, tetapi hal berikut bergantung pada
lingkunganmu: **HTTPS/reverse proxy**, **backup database berkala**, dan
**timezone** (`config/app.php` masih `UTC`, sehingga tenggat tugas dan timer
kuis akan tampil selisih 7 jam dari WIB).

---

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
