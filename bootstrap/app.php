<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Aplikasi berjalan di belakang reverse proxy (Caddy) yang menangani
         * HTTPS. Tanpa ini Laravel melihat permintaan sebagai HTTP biasa dan
         * menghasilkan tautan `http://` — termasuk tautan verifikasi email dan
         * reset password, yang bisa gagal atau ditolak browser.
         *
         * Mempercayai seluruh proxy ('*') aman di sini karena container app
         * TIDAK dipublikasikan ke host: satu-satunya jalan masuk adalah Caddy,
         * sehingga header X-Forwarded-* mustahil dipalsukan dari luar.
         */
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
