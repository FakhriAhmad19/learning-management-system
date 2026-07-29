<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Support\Facades\Auth;

class LearnController extends Controller
{
    /**
     * Ruang Belajar (Course Player) berbasis bacaan.
     * Hanya dapat diakses siswa dengan pendaftaran aktif/selesai.
     */
    public function show(Course $course, ?string $lesson = null)
    {
        $user = Auth::user();

        // Gerbang akses: wajib punya enrollment aktif atau sudah selesai
        $enrollment = $course->enrollments()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'completed'])
            ->first();

        if (! $enrollment) {
            return redirect()
                ->route('courses.show', $course->slug)
                ->with('error', 'Kamu belum terdaftar di kelas ini atau pendaftaran belum dikonfirmasi.');
        }

        // Muat kurikulum lengkap (modul + materi terurut + kuis & tugas bab)
        $course->load(['modules.lessons', 'modules.quiz', 'modules.assignments', 'instructor']);

        // Kumpulkan seluruh lesson secara berurutan untuk navigasi prev/next
        $allLessons = $course->modules->flatMap->lessons->values();

        if ($allLessons->isEmpty()) {
            return redirect()
                ->route('courses.show', $course->slug)
                ->with('error', 'Kurikulum kelas ini belum memiliki materi.');
        }

        // Tentukan materi aktif: berdasarkan slug pada URL, atau materi pertama
        $currentLesson = $lesson
            ? $allLessons->firstWhere('slug', $lesson)
            : $allLessons->first();

        abort_if($currentLesson === null, 404);

        $currentIndex = $allLessons->search(fn ($l) => $l->id === $currentLesson->id);
        $prevLesson = $currentIndex > 0 ? $allLessons[$currentIndex - 1] : null;
        $nextLesson = $currentIndex < $allLessons->count() - 1 ? $allLessons[$currentIndex + 1] : null;

        // ID materi yang sudah diselesaikan siswa (untuk centang & tombol status)
        $completedLessonIds = $user->completedLessons()
            ->whereIn('lessons.id', $allLessons->pluck('id'))
            ->pluck('lessons.id')
            ->all();

        // ID kuis yang sudah LULUS (untuk centang di sidebar)
        $quizIds = $course->modules->pluck('quiz.id')->filter();
        $passedQuizIds = $user->quizAttempts()
            ->whereIn('quiz_id', $quizIds)
            ->where('passed', true)
            ->pluck('quiz_id')
            ->unique()
            ->all();

        // Status tugas per bab: sudah lulus (centang) vs masih menunggu penilaian
        $assignments = $course->modules->flatMap->assignments;
        $submissions = $user->assignmentSubmissions()
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->get()
            ->keyBy('assignment_id');

        $passedAssignmentIds = [];
        $awaitingGradingAssignmentIds = [];

        foreach ($assignments as $assignment) {
            $submission = $submissions->get($assignment->id);
            if (! $submission) {
                continue;
            }

            // setRelation menghindari query ulang saat isPassed() membaca passing_score
            $submission->setRelation('assignment', $assignment);

            if ($submission->isPassed()) {
                $passedAssignmentIds[] = $assignment->id;
            } elseif (! $submission->isGraded()) {
                $awaitingGradingAssignmentIds[] = $assignment->id;
            }
        }

        // Tanya-jawab materi ini: pertanyaan terbaru di atas, balasan urut lama→baru
        $discussions = $currentLesson->discussions()
            ->whereNull('parent_id')
            ->with(['author', 'replies.author'])
            ->latest()
            ->get();

        return view('learn.show', compact(
            'course',
            'enrollment',
            'currentLesson',
            'discussions',
            'prevLesson',
            'nextLesson',
            'completedLessonIds',
            'passedQuizIds',
            'passedAssignmentIds',
            'awaitingGradingAssignmentIds',
        ));
    }

    /**
     * Tandai sebuah materi sebagai selesai, lalu perbarui persentase progres.
     */
    public function complete(Course $course, string $lesson)
    {
        $user = Auth::user();

        $enrollment = $course->enrollments()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'completed'])
            ->first();

        if (! $enrollment) {
            return redirect()
                ->route('courses.show', $course->slug)
                ->with('error', 'Kamu belum terdaftar di kelas ini.');
        }

        $course->load('modules.lessons');
        $allLessons = $course->modules->flatMap->lessons->values();
        $lessonModel = $allLessons->firstWhere('slug', $lesson);
        abort_if($lessonModel === null, 404);

        // Tandai selesai (idempotent — tidak menduplikasi bila diklik ulang)
        $user->completedLessons()->syncWithoutDetaching([
            $lessonModel->id => ['completed_at' => now()],
        ]);

        // Hitung ulang progres (materi + kuis sebagai satu kesatuan unit)
        $enrollment->recalculateProgress();

        // Arahkan ke materi berikutnya bila ada
        $index = $allLessons->search(fn ($l) => $l->id === $lessonModel->id);
        $next = $index < $allLessons->count() - 1 ? $allLessons[$index + 1] : null;
        $target = $next
            ? route('learn.show', [$course->slug, $next->slug])
            : route('learn.show', [$course->slug, $lessonModel->slug]);

        return redirect($target)->with(
            'success',
            $enrollment->progress_percentage >= 100
                ? 'Selamat! Kamu telah menyelesaikan seluruh kelas ini. 🎉'
                : 'Materi ditandai selesai. Lanjut ke materi berikutnya.'
        );
    }
}
