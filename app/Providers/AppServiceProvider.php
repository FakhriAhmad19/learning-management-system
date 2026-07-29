<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Seluruh UI berbahasa Indonesia, termasuk penanggalan relatif
        // seperti diffForHumans() ("2 menit yang lalu") di daftar notifikasi.
        Carbon::setLocale('id');
    }
}
