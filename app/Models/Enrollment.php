<?php

namespace App\Models;

use App\Notifications\CourseCompleted;
use App\Notifications\PathCourseUnlocked;
use App\Services\GamificationService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'amount_paid',
        'status',
        'progress_percentage',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'amount_paid' => 'decimal:2',
    ];

    /**
     * Relasi: Pendaftaran ini milik satu Siswa (User)
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relasi: Pendaftaran ini merujuk ke satu Kursus
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Hitung ulang persentase progres.
     *
     * Setiap materi (lesson), kuis, DAN tugas dihitung sebagai satu unit.
     * Kursus dianggap 100% (dan status "completed") hanya bila seluruh materi
     * telah diselesaikan, seluruh kuis LULUS, dan seluruh tugas sudah dinilai
     * dengan nilai mencapai batas lulus.
     */
    public function recalculateProgress(): void
    {
        $moduleIds = Module::where('course_id', $this->course_id)->pluck('id');

        $lessonIds = Lesson::whereIn('module_id', $moduleIds)->pluck('id');
        $quizIds = Quiz::whereIn('module_id', $moduleIds)->pluck('id');
        $assignmentIds = Assignment::whereIn('module_id', $moduleIds)->pluck('id');

        $totalUnits = $lessonIds->count() + $quizIds->count() + $assignmentIds->count();

        $completedLessons = DB::table('lesson_user')
            ->where('user_id', $this->user_id)
            ->whereIn('lesson_id', $lessonIds)
            ->count();

        $passedQuizzes = QuizAttempt::where('user_id', $this->user_id)
            ->whereIn('quiz_id', $quizIds)
            ->where('passed', true)
            ->pluck('quiz_id')
            ->unique()
            ->count();

        // Tugas dihitung selesai hanya bila sudah dinilai DAN mencapai batas lulus
        $passedAssignments = AssignmentSubmission::query()
            ->join('assignments', 'assignments.id', '=', 'assignment_submissions.assignment_id')
            ->where('assignment_submissions.user_id', $this->user_id)
            ->whereIn('assignment_submissions.assignment_id', $assignmentIds)
            ->whereNotNull('assignment_submissions.graded_at')
            ->whereColumn('assignment_submissions.score', '>=', 'assignments.passing_score')
            ->count();

        $completedUnits = $completedLessons + $passedQuizzes + $passedAssignments;

        $percentage = $totalUnits > 0 ? (int) round($completedUnits / $totalUnits * 100) : 0;

        $wasCompleted = $this->status === 'completed';

        $this->progress_percentage = $percentage;
        if ($percentage >= 100) {
            $this->status = 'completed';
            $this->completed_at = $this->completed_at ?? now();
        }
        $this->save();

        // Ucapan selamat + tautan sertifikat, hanya sekali saat status berubah
        if (! $wasCompleted && $this->status === 'completed') {
            $this->student?->notify(new CourseCompleted($this->course));
            $this->notifyUnlockedPathCourses();
        }

        // Poin & badge direkonsiliasi di sini karena seluruh jalur yang mengubah
        // pencapaian siswa (materi selesai, kuis lulus, tugas dinilai) bermuara
        // ke pemanggilan ini — satu titik, tidak ada yang terlewat.
        app(GamificationService::class)->sync($this);
    }

    /**
     * Menyelesaikan kursus ini bisa membuka kursus berikutnya pada jalur belajar
     * yang diikuti siswa. Kabari agar siswa tahu ada tahap baru yang terbuka.
     */
    private function notifyUnlockedPathCourses(): void
    {
        $student = $this->student;

        if ($student === null) {
            return;
        }

        $paths = $student->learningPaths()
            ->whereHas('courses', fn ($q) => $q->whereKey($this->course_id))
            ->with('courses')
            ->get();

        foreach ($paths as $path) {
            $courses = $path->courses;
            $position = $courses->search(fn (Course $c) => $c->id === $this->course_id);

            if ($position === false) {
                continue;
            }

            $next = $courses->get($position + 1);

            if ($next !== null && $path->isCourseUnlockedFor($student, $next)) {
                $student->notify(new PathCourseUnlocked($path, $next));
            }
        }
    }
}
