<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lama Penyimpanan Cadangan
    |--------------------------------------------------------------------------
    |
    | Cadangan yang lebih tua dari jumlah hari ini akan dihapus otomatis
    | setelah cadangan baru berhasil dibuat.
    |
    */

    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Opsi mysqldump
    |--------------------------------------------------------------------------
    |
    | --single-transaction  : konsisten tanpa mengunci tabel (InnoDB)
    | --no-tablespaces      : menghindari kebutuhan hak akses PROCESS
    | --ssl-verify-server-cert=0 : MySQL 8 di Docker memakai sertifikat
    |   self-signed, yang ditolak klien tanpa opsi ini. Koneksinya tetap
    |   terenkripsi, hanya verifikasi sertifikatnya yang dilewati. Bila
    |   database berada di jaringan publik dengan sertifikat sah, hapus
    |   opsi ini lewat BACKUP_MYSQLDUMP_OPTIONS.
    |
    | PENTING: opsi TLS berbeda antar klien. Image Docker proyek ini memakai
    | klien MariaDB (--ssl-verify-server-cert), sedangkan klien resmi Oracle
    | memakai --ssl-mode dan akan menolak opsi di atas. Karena itu CI
    | menimpanya lewat BACKUP_MYSQLDUMP_OPTIONS.
    |
    */

    'mysqldump_options' => env(
        'BACKUP_MYSQLDUMP_OPTIONS',
        '--single-transaction --no-tablespaces --ssl-verify-server-cert=0'
    ),

    /*
    |--------------------------------------------------------------------------
    | Jam Penjadwalan Harian
    |--------------------------------------------------------------------------
    |
    | Mengikuti zona waktu aplikasi (Asia/Jakarta).
    |
    | CATATAN: mengubah nilai ini TIDAK langsung berlaku pada container
    | `scheduler` yang sedang berjalan — terverifikasi saat pengujian bahwa
    | jadwal baru baru dipakai setelah container direstart:
    |
    |   docker compose restart scheduler
    |
    */

    'daily_at' => env('BACKUP_DAILY_AT', '02:00'),

];
