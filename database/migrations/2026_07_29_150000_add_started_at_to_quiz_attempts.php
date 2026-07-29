<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            // Jam mulai dicatat di database, bukan di sesi, supaya memuat ulang
            // halaman (atau berpindah browser) tidak menyetel ulang hitungan waktu.
            // completed_at masih null = percobaan sedang berjalan.
            $table->dateTime('started_at')->nullable()->after('quiz_id');
            $table->index(['user_id', 'quiz_id']);
        });

        // Percobaan lama semuanya sudah selesai; isi started_at agar tidak
        // pernah terbaca sebagai "sedang dikerjakan".
        DB::table('quiz_attempts')
            ->whereNull('started_at')
            ->update(['started_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'quiz_id']);
            $table->dropColumn('started_at');
        });
    }
};
