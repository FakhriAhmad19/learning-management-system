<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    protected $fillable = [
        'module_id',
        'title',
        'passing_score',
        'max_attempts',
        'time_limit_minutes',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /**
     * Berapa kali seorang siswa sudah mengerjakan kuis ini.
     * Percobaan yang kedaluwarsa ikut dihitung agar batas tidak bisa diakali
     * dengan sengaja membiarkan waktu habis.
     */
    public function attemptsUsedBy(User $user): int
    {
        return $this->attempts()->where('user_id', $user->id)->count();
    }

    /**
     * Sisa percobaan, atau null bila kuis tidak dibatasi.
     */
    public function remainingAttemptsFor(User $user): ?int
    {
        if ($this->max_attempts === null) {
            return null;
        }

        return max(0, $this->max_attempts - $this->attemptsUsedBy($user));
    }

    public function canBeAttemptedBy(User $user): bool
    {
        return $this->remainingAttemptsFor($user) !== 0;
    }

    /**
     * Kuis yang memuat soal esai tidak bisa dinilai sepenuhnya otomatis.
     */
    public function hasEssayQuestions(): bool
    {
        return $this->questions()->where('type', Question::TYPE_ESSAY)->exists();
    }

    public function hasTimeLimit(): bool
    {
        return $this->time_limit_minutes !== null && $this->time_limit_minutes > 0;
    }

    /**
     * Percobaan yang jamnya sedang berjalan untuk siswa ini, bila ada.
     * Inilah yang membuat hitungan waktu bertahan saat halaman dimuat ulang.
     */
    public function inProgressAttemptFor(User $user): ?QuizAttempt
    {
        return $this->attempts()
            ->where('user_id', $user->id)
            ->inProgress()
            ->latest('started_at')
            ->first();
    }

    /**
     * Percobaan terakhir yang sudah benar-benar selesai (untuk menampilkan nilai).
     */
    public function lastCompletedAttemptFor(User $user): ?QuizAttempt
    {
        return $this->attempts()
            ->where('user_id', $user->id)
            ->completed()
            ->latest('completed_at')
            ->first();
    }
}
