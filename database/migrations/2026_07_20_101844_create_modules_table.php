<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            // Setiap modul dimiliki oleh satu Kursus
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');

            $table->string('title');
            $table->integer('order')->default(1); // Urutan bab dalam kursus (Bab 1, Bab 2, dst)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
