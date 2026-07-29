<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tanya-jawab per materi. Threading sengaja hanya satu tingkat:
        // parent_id null = pertanyaan, terisi = balasan atas pertanyaan itu.
        Schema::create('discussions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('discussions')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['lesson_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussions');
    }
};
