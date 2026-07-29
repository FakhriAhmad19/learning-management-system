<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu baris per poin yang pernah diberikan. Kunci unik di bawah membuat
        // pemberian poin idempoten: mengulang sinkronisasi tidak menggandakan poin.
        Schema::create('point_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('awardable_type');
            $table->unsignedBigInteger('awardable_id');
            $table->unsignedInteger('points');
            $table->timestamps();

            $table->unique(['user_id', 'type', 'awardable_type', 'awardable_id'], 'point_awards_unique_source');
            $table->index(['course_id', 'user_id']);
        });

        // Badge yang sudah diraih. Definisinya ada di kode (App\Gamification\Badges),
        // tabel ini hanya mencatat siapa meraih apa dan kapan.
        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('badge');
            $table->timestamps();

            $table->unique(['user_id', 'badge']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('point_awards');
    }
};
