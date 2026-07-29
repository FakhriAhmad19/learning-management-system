<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            // Setiap materi berada di bawah satu Modul
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');

            $table->string('title');
            $table->string('slug');
            $table->longText('content')->nullable(); // Teks materi/artikel bacaan (RichText)
            $table->string('attachment')->nullable(); // Berkas lampiran (PDF / Docx / ZIP)
            $table->boolean('is_free_preview')->default(false); // Bisa dibaca gratis tanpa daftar?
            $table->integer('order')->default(1); // Urutan materi dalam bab
            $table->timestamps();

            // Slug materi unik dalam satu modul
            $table->unique(['module_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
