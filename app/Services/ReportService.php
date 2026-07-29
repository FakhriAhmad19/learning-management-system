<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Sumber tunggal data laporan: dipakai halaman Buku Nilai, halaman Laporan,
 * dan seluruh ekspor CSV — supaya angka di layar dan di berkas selalu sama.
 */
class ReportService
{
    /**
     * Kursus yang boleh dilihat pengguna: semua untuk Admin, miliknya untuk Instructor.
     */
    public function visibleCourses(?int $instructorId): Collection
    {
        return Course::query()
            ->when($instructorId !== null, fn (Builder $q) => $q->where('instructor_id', $instructorId))
            ->with(['instructor', 'category'])
            ->orderBy('title')
            ->get();
    }

    /**
     * Kolom penilaian sebuah kursus: seluruh kuis lalu seluruh tugas.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function assessmentColumns(Course $course): Collection
    {
        $quizzes = Quiz::whereHas('module', fn (Builder $q) => $q->where('course_id', $course->id))
            ->with('module')
            ->get()
            ->map(fn (Quiz $quiz) => [
                'key' => 'quiz-'.$quiz->id,
                'id' => $quiz->id,
                'type' => 'quiz',
                'title' => $quiz->title,
                'module' => $quiz->module->title,
                'max' => 100,
                'passing' => $quiz->passing_score,
            ]);

        $assignments = Assignment::whereHas('module', fn (Builder $q) => $q->where('course_id', $course->id))
            ->with('module')
            ->get()
            ->map(fn (Assignment $assignment) => [
                'key' => 'assignment-'.$assignment->id,
                'id' => $assignment->id,
                'type' => 'assignment',
                'title' => $assignment->title,
                'module' => $assignment->module->title,
                'max' => $assignment->max_score,
                'passing' => $assignment->passing_score,
            ]);

        return $quizzes->concat($assignments)->values();
    }

    /**
     * Satu baris per siswa terdaftar berisi nilai untuk setiap kolom penilaian.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function gradebookRows(Course $course): Collection
    {
        $columns = $this->assessmentColumns($course);

        $enrollments = Enrollment::where('course_id', $course->id)
            ->whereIn('status', ['active', 'completed'])
            ->with('student')
            ->get();

        $studentIds = $enrollments->pluck('user_id');

        // Percobaan kuis TERBAIK per siswa per kuis (yang sudah dikirim saja)
        $bestAttempts = QuizAttempt::whereIn('user_id', $studentIds)
            ->whereIn('quiz_id', $columns->where('type', 'quiz')->pluck('id'))
            ->completed()
            ->with('quiz')
            ->get()
            ->groupBy(fn (QuizAttempt $attempt) => $attempt->user_id.'-'.$attempt->quiz_id)
            ->map(fn (Collection $attempts) => $attempts->sortByDesc('score')->first());

        $submissions = AssignmentSubmission::whereIn('user_id', $studentIds)
            ->whereIn('assignment_id', $columns->where('type', 'assignment')->pluck('id'))
            ->get()
            ->keyBy(fn (AssignmentSubmission $s) => $s->user_id.'-'.$s->assignment_id);

        return $enrollments->map(function (Enrollment $enrollment) use ($columns, $bestAttempts, $submissions) {
            $cells = [];

            foreach ($columns as $column) {
                $cells[$column['key']] = $column['type'] === 'quiz'
                    ? $this->quizCell($bestAttempts->get($enrollment->user_id.'-'.$column['id']))
                    : $this->assignmentCell($submissions->get($enrollment->user_id.'-'.$column['id']), $column['passing']);
            }

            return [
                'student' => $enrollment->student,
                'progress' => $enrollment->progress_percentage,
                'status' => $enrollment->status,
                'cells' => $cells,
            ];
        })->values();
    }

    /**
     * @return array{score: ?int, passed: bool, pending: bool}
     */
    private function quizCell(?QuizAttempt $attempt): array
    {
        return [
            'score' => $attempt?->score,
            'passed' => (bool) $attempt?->passed,
            // Ada jawaban esai yang menunggu dinilai pengajar
            'pending' => $attempt !== null && $attempt->needsReview(),
        ];
    }

    /**
     * @return array{score: ?int, passed: bool, pending: bool}
     */
    private function assignmentCell(?AssignmentSubmission $submission, int $passing): array
    {
        return [
            'score' => $submission?->score,
            'passed' => $submission !== null
                && $submission->score !== null
                && $submission->graded_at !== null
                && $submission->score >= $passing,
            // Sudah dikumpulkan tetapi belum dinilai pengajar
            'pending' => $submission !== null && $submission->graded_at === null,
        ];
    }

    /**
     * Ringkasan agregat per kursus untuk halaman Laporan.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function courseSummaries(?int $instructorId): Collection
    {
        return $this->visibleCourses($instructorId)->map(function (Course $course) {
            $enrollments = Enrollment::where('course_id', $course->id)
                ->whereIn('status', ['active', 'completed'])
                ->get();

            $total = $enrollments->count();
            $completed = $enrollments->where('status', 'completed')->count();

            return [
                'course' => $course,
                'students' => $total,
                'active' => $total - $completed,
                'completed' => $completed,
                'average_progress' => $total > 0
                    ? (int) round($enrollments->avg('progress_percentage'))
                    : 0,
                'completion_rate' => $total > 0
                    ? (int) round($completed / $total * 100)
                    : 0,
                'pending_grading' => $this->pendingGradingCount($course),
            ];
        });
    }

    /**
     * Jumlah pekerjaan yang menunggu dinilai pengajar: tugas + esai kuis.
     */
    public function pendingGradingCount(Course $course): int
    {
        $assignmentIds = Assignment::whereHas('module', fn (Builder $q) => $q->where('course_id', $course->id))->pluck('id');
        $quizIds = Quiz::whereHas('module', fn (Builder $q) => $q->where('course_id', $course->id))->pluck('id');

        $pendingSubmissions = AssignmentSubmission::whereIn('assignment_id', $assignmentIds)
            ->whereNull('graded_at')
            ->count();

        $pendingAttempts = QuizAttempt::whereIn('quiz_id', $quizIds)
            ->completed()
            ->where('expired', false)
            ->whereNull('graded_at')
            ->count();

        return $pendingSubmissions + $pendingAttempts;
    }

    /**
     * Daftar peserta sebuah kursus beserta progresnya.
     *
     * @return Collection<int, Enrollment>
     */
    public function participants(Course $course): Collection
    {
        return Enrollment::where('course_id', $course->id)
            ->whereIn('status', ['active', 'completed'])
            ->with('student')
            ->join('users', 'users.id', '=', 'enrollments.user_id')
            ->orderBy('users.name')
            ->select('enrollments.*')
            ->get();
    }
}
