<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            // null = tanpa batas (perilaku lama tetap berlaku untuk kuis yang sudah ada)
            $table->unsignedTinyInteger('max_attempts')->nullable()->after('passing_score');
            $table->unsignedSmallInteger('time_limit_minutes')->nullable()->after('max_attempts');
        });

        Schema::table('questions', function (Blueprint $table) {
            // 'true_false' hanyalah pilihan ganda dengan dua opsi baku,
            // sehingga mesin penilaian tetap sama.
            $table->string('type')->default('multiple_choice')->after('quiz_id');
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            // Percobaan yang ditolak karena waktu habis dicatat agar tetap
            // terhitung sebagai percobaan terpakai.
            $table->boolean('expired')->default(false)->after('passed');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropColumn('expired');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn(['max_attempts', 'time_limit_minutes']);
        });
    }
};
