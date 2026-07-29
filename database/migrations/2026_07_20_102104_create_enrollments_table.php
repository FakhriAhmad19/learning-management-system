<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            // Relasi ke Siswa dan Kursus yang diikuti
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');

            $table->decimal('amount_paid', 12, 2)->default(0); // Nominal bayar
            $table->enum('status', ['pending', 'active', 'completed', 'cancelled'])->default('active');
            $table->integer('progress_percentage')->default(0); // Misal: 50%
            $table->timestamp('completed_at')->nullable(); // Waktu selesai kursus

            $table->timestamps();

            // Mencegah siswa terdaftar ganda di kursus yang sama
            $table->unique(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
