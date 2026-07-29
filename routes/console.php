<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Cadangan database harian.
 *
 * withoutOverlapping: bila cadangan sebelumnya belum selesai (database besar,
 * disk lambat), jangan menjalankan yang baru di atasnya.
 * onOneServer: aman bila nanti aplikasi dijalankan lebih dari satu instans.
 *
 * Dijalankan oleh container `scheduler` (php artisan schedule:work).
 */
Schedule::command('backup:database')
    ->dailyAt(config('backup.daily_at'))
    ->withoutOverlapping()
    ->onOneServer();
