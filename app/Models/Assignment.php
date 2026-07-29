<?php

namespace App\Models;

use App\Notifications\AssignmentPublished;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Notification;

class Assignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'title',
        'description',
        'due_date',
        'max_score',
        'passing_score',
    ];

    protected $casts = [
        'due_date' => 'datetime',
    ];

    /**
     * Begitu tugas dibuat, seluruh siswa yang sedang mengikuti kelas diberi tahu
     * (lonceng in-app + email) agar tidak perlu memeriksa halaman kelas manual.
     */
    protected static function booted(): void
    {
        static::created(function (Assignment $assignment): void {
            $courseId = $assignment->module?->course_id;
            if (! $courseId) {
                return;
            }

            $students = User::whereHas(
                'enrollments',
                fn ($q) => $q->where('course_id', $courseId)->whereIn('status', ['active', 'completed'])
            )->get();

            if ($students->isNotEmpty()) {
                Notification::send($students, new AssignmentPublished($assignment));
            }
        });
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    /**
     * Pengumpulan milik seorang siswa (satu tugas = satu pengumpulan per siswa).
     */
    public function submissionFor(User $user): ?AssignmentSubmission
    {
        return $this->submissions()->where('user_id', $user->id)->first();
    }

    /**
     * Tugas dianggap terlambat bila lewat tenggat (tenggat opsional).
     */
    public function isOverdue(): bool
    {
        return $this->due_date !== null && $this->due_date->isPast();
    }
}
