<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class QuizAttempt extends Model
{
    protected $fillable = [
        'user_id',
        'quiz_id',
        'score',
        'passed',
        'expired',
        'answers',
        'started_at',
        'completed_at',
        'graded_at',
    ];

    protected $casts = [
        'passed' => 'boolean',
        'expired' => 'boolean',
        'started_at' => 'datetime',
        'answers' => 'array',
        'completed_at' => 'datetime',
        'graded_at' => 'datetime',
    ];

    /**
     * Percobaan yang sudah dikirim (atau sudah difinalisasi karena waktu habis).
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereNotNull('completed_at');
    }

    /**
     * Percobaan yang jamnya sedang berjalan dan belum dikirim.
     */
    public function scopeInProgress(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    public function isInProgress(): bool
    {
        return $this->completed_at === null;
    }

    /**
     * Batas akhir pengerjaan berdasarkan jam mulai yang tersimpan di database.
     */
    public function deadline(): ?Carbon
    {
        if ($this->started_at === null || ! $this->quiz->hasTimeLimit()) {
            return null;
        }

        return $this->started_at->copy()->addMinutes($this->quiz->time_limit_minutes);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function answerGrades(): HasMany
    {
        return $this->hasMany(QuizAnswerGrade::class);
    }

    /**
     * Sudah dikirim tetapi masih ada jawaban esai yang belum dinilai pengajar.
     *
     * Pengecekan soal esai dilakukan terakhir (dan hanya bila perlu) supaya
     * percobaan pada kuis tanpa esai tidak pernah tampil "menunggu penilaian",
     * apa pun cara baris itu dibuat.
     */
    public function needsReview(): bool
    {
        if ($this->isInProgress() || $this->expired || $this->graded_at !== null) {
            return false;
        }

        return $this->quiz->hasEssayQuestions();
    }

    public function isFullyGraded(): bool
    {
        return $this->graded_at !== null;
    }

    /**
     * Jawaban teks siswa untuk sebuah soal esai.
     */
    public function essayAnswerFor(Question $question): ?string
    {
        $value = ($this->answers ?? [])[$question->id] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Hitung ulang nilai percobaan ini.
     *
     * Setiap soal bernilai `points`. Soal beropsi mendapat poin penuh bila
     * jawabannya benar; soal esai mendapat poin dari penilaian pengajar.
     * `graded_at` baru terisi setelah seluruh esai dinilai — sampai saat itu
     * percobaan berstatus "menunggu penilaian" dan belum dianggap lulus.
     */
    public function recalculateScore(): void
    {
        $questions = $this->quiz->questions()->with('options')->get();
        $answers = $this->answers ?? [];

        $totalPoints = (int) $questions->sum('points');
        $earnedPoints = 0;
        $essayCount = 0;
        $gradedEssayCount = 0;

        $grades = $this->answerGrades()->get()->keyBy('question_id');

        foreach ($questions as $question) {
            if ($question->isEssay()) {
                $essayCount++;
                $grade = $grades->get($question->id);

                if ($grade !== null) {
                    $gradedEssayCount++;
                    $earnedPoints += min($grade->score, $question->points);
                }

                continue;
            }

            $selectedOptionId = $answers[$question->id] ?? null;
            if ($selectedOptionId) {
                $option = $question->options->firstWhere('id', (int) $selectedOptionId);
                if ($option && $option->is_correct) {
                    $earnedPoints += $question->points;
                }
            }
        }

        $fullyGraded = $essayCount === $gradedEssayCount;
        $score = $totalPoints > 0 ? (int) round($earnedPoints / $totalPoints * 100) : 0;

        $this->score = $score;
        // Belum boleh dinyatakan lulus selama masih ada esai yang menunggu nilai
        $this->passed = $fullyGraded && $score >= $this->quiz->passing_score;
        $this->graded_at = $fullyGraded ? ($this->graded_at ?? now()) : null;
        $this->save();
    }
}
