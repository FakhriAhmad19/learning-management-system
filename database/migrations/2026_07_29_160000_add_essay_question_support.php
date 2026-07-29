<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Bobot soal. Default 1 membuat perilaku kuis lama persis sama
            // (semua soal berbobot sama), tapi esai bisa diberi bobot lebih.
            $table->unsignedSmallInteger('points')->default(1)->after('type');
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            // null selama masih ada jawaban esai yang menunggu dinilai pengajar
            $table->dateTime('graded_at')->nullable()->after('completed_at');
        });

        // Nilai & umpan balik per jawaban esai
        Schema::create('quiz_answer_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('score')->default(0);
            $table->text('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['quiz_attempt_id', 'question_id']);
        });

        // Percobaan lama seluruhnya dinilai otomatis, jadi sudah final.
        DB::table('quiz_attempts')
            ->whereNotNull('completed_at')
            ->whereNull('graded_at')
            ->update(['graded_at' => DB::raw('completed_at')]);
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_answer_grades');

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropColumn('graded_at');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('points');
        });
    }
};
