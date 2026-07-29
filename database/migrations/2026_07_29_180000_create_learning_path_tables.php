<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jalur belajar: rangkaian kursus berurutan (mis. "Backend dari Nol")
        Schema::create('learning_paths', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        // Urutan kursus di dalam jalur. Sebuah kursus boleh berada di
        // beberapa jalur sekaligus.
        Schema::create('learning_path_course', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_path_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('order')->default(1);
            $table->timestamps();

            $table->unique(['learning_path_id', 'course_id']);
        });

        // Siswa yang mengikuti jalur. Prasyarat HANYA berlaku bagi jalur yang
        // diikuti siswa — kursus yang sama tetap bisa diambil lepas dari katalog
        // bila siswa tidak bergabung ke jalurnya.
        Schema::create('learning_path_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_path_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['learning_path_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_path_user');
        Schema::dropIfExists('learning_path_course');
        Schema::dropIfExists('learning_paths');
    }
};
