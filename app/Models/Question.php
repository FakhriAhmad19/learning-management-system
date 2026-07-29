<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';

    public const TYPE_TRUE_FALSE = 'true_false';

    public const TYPE_ESSAY = 'essay';

    public const TRUE_LABEL = 'Benar';

    public const FALSE_LABEL = 'Salah';

    protected $fillable = [
        'quiz_id',
        'type',
        'points',
        'question',
        'order',
        // Bukan kolom database — lihat mutator di bawah
        'true_false_answer',
    ];

    /**
     * Agar Filament bisa mengisi radio Benar/Salah dari data yang tersimpan.
     */
    protected $appends = ['true_false_answer'];

    /**
     * Jawaban benar untuk soal Benar/Salah, disimpan sementara di luar
     * $attributes supaya tidak ikut ditulis sebagai kolom.
     */
    protected ?string $trueFalseAnswer = null;

    /**
     * Soal Benar/Salah tetap disimpan sebagai dua QuestionOption biasa,
     * sehingga mesin penilaian di QuizController tidak perlu tahu bedanya.
     */
    protected static function booted(): void
    {
        static::saved(function (Question $question): void {
            if (! $question->isTrueFalse() || $question->trueFalseAnswer === null) {
                return;
            }

            $isTrue = $question->trueFalseAnswer === 'benar';

            $question->options()->delete();
            $question->options()->createMany([
                ['option_text' => self::TRUE_LABEL, 'is_correct' => $isTrue],
                ['option_text' => self::FALSE_LABEL, 'is_correct' => ! $isTrue],
            ]);
            $question->unsetRelation('options');
        });
    }

    public function setTrueFalseAnswerAttribute(?string $value): void
    {
        $this->trueFalseAnswer = $value;
    }

    public function getTrueFalseAnswerAttribute(): ?string
    {
        if (! $this->isTrueFalse() || ! $this->exists) {
            return $this->trueFalseAnswer;
        }

        $correct = $this->options->firstWhere('is_correct', true);

        if ($correct === null) {
            return null;
        }

        return $correct->option_text === self::TRUE_LABEL ? 'benar' : 'salah';
    }

    public function isTrueFalse(): bool
    {
        return $this->type === self::TYPE_TRUE_FALSE;
    }

    /**
     * Soal esai dijawab dengan teks bebas dan dinilai manual oleh pengajar.
     */
    public function isEssay(): bool
    {
        return $this->type === self::TYPE_ESSAY;
    }

    /**
     * Soal berbasis opsi dapat dinilai otomatis.
     */
    public function isAutoGraded(): bool
    {
        return ! $this->isEssay();
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class);
    }

    public function correctOption(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->where('is_correct', true);
    }
}
