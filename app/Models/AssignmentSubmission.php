<?php

namespace App\Models;

use App\Notifications\AssignmentGraded;
use App\Notifications\SubmissionReceived;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'user_id',
        'content',
        'attachment',
        'submitted_at',
        'score',
        'feedback',
        'graded_at',
        'graded_by',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
    ];

    /**
     * Setiap perubahan pengumpulan (terutama saat dinilai) langsung memperbarui
     * progres kursus siswa, dari mana pun perubahan itu berasal.
     */
    protected static function booted(): void
    {
        static::saved(function (AssignmentSubmission $submission): void {
            $courseId = $submission->assignment?->module?->course_id;
            if (! $courseId) {
                return;
            }

            Enrollment::where('user_id', $submission->user_id)
                ->where('course_id', $courseId)
                ->first()
                ?->recalculateProgress();
        });

        // Pengajar diberi tahu bahwa ada tugas masuk yang perlu dinilai
        static::created(function (AssignmentSubmission $submission): void {
            $instructor = $submission->assignment?->module?->course?->instructor;

            $instructor?->notify(new SubmissionReceived($submission));
        });

        // Siswa diberi tahu saat tugasnya selesai dinilai (sekali, saat penilaian terjadi)
        static::updated(function (AssignmentSubmission $submission): void {
            if (! $submission->wasChanged('graded_at') || $submission->graded_at === null) {
                return;
            }

            $submission->student?->notify(new AssignmentGraded($submission));
        });
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function isGraded(): bool
    {
        return $this->graded_at !== null && $this->score !== null;
    }

    /**
     * Lulus bila sudah dinilai dan nilainya mencapai batas lulus tugas.
     */
    public function isPassed(): bool
    {
        return $this->isGraded() && $this->score >= $this->assignment->passing_score;
    }

    /**
     * Label status untuk ditampilkan ke siswa.
     */
    public function statusLabel(): string
    {
        if (! $this->isGraded()) {
            return 'Menunggu Penilaian';
        }

        return $this->isPassed() ? 'Lulus' : 'Belum Lulus';
    }
}
