<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PointAward extends Model
{
    use HasFactory;

    /** Poin untuk setiap jenis pencapaian. */
    public const POINTS = [
        self::TYPE_LESSON => 10,
        self::TYPE_QUIZ => 25,
        self::TYPE_ASSIGNMENT => 30,
        self::TYPE_COURSE => 100,
    ];

    public const TYPE_LESSON = 'lesson_completed';

    public const TYPE_QUIZ = 'quiz_passed';

    public const TYPE_ASSIGNMENT = 'assignment_passed';

    public const TYPE_COURSE = 'course_completed';

    protected $fillable = [
        'user_id',
        'course_id',
        'type',
        'awardable_type',
        'awardable_id',
        'points',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function awardable(): MorphTo
    {
        return $this->morphTo();
    }

    public static function pointsFor(string $type): int
    {
        return self::POINTS[$type] ?? 0;
    }

    /**
     * Label ramah pengguna untuk riwayat poin.
     */
    public function label(): string
    {
        return match ($this->type) {
            self::TYPE_LESSON => 'Menyelesaikan materi',
            self::TYPE_QUIZ => 'Lulus kuis',
            self::TYPE_ASSIGNMENT => 'Tugas dinilai lulus',
            self::TYPE_COURSE => 'Menyelesaikan kursus',
            default => 'Pencapaian',
        };
    }
}
