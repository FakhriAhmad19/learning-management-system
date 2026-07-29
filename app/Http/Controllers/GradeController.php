<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Quiz;
use Illuminate\Support\Facades\Auth;

class GradeController extends Controller
{
    /**
     * "Nilai Saya" — rekap nilai kuis & tugas siswa untuk seluruh kelas yang diikuti.
     */
    public function index()
    {
        $user = Auth::user();

        $enrollments = $user->enrollments()
            ->whereIn('status', ['active', 'completed'])
            ->with('course.instructor')
            ->get();

        $courseIds = $enrollments->pluck('course_id');

        // Nilai kuis: ambil percobaan TERBAIK per kuis
        $quizzes = Quiz::whereHas('module', fn ($q) => $q->whereIn('course_id', $courseIds))
            ->with('module')
            ->get();

        // Percobaan yang masih berjalan belum punya nilai — jangan ikut dihitung
        $bestAttempts = $user->quizAttempts()
            ->whereIn('quiz_id', $quizzes->pluck('id'))
            ->completed()
            ->get()
            ->groupBy('quiz_id')
            ->map(fn ($attempts) => $attempts->sortByDesc('score')->first());

        // Nilai tugas: satu pengumpulan per siswa per tugas
        $assignments = Assignment::whereHas('module', fn ($q) => $q->whereIn('course_id', $courseIds))
            ->with('module')
            ->get();

        $submissions = $user->assignmentSubmissions()
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->get()
            ->keyBy('assignment_id');

        // Susun baris nilai per kursus agar view tinggal menampilkan
        $report = $enrollments->map(function ($enrollment) use ($quizzes, $bestAttempts, $assignments, $submissions) {
            $courseQuizzes = $quizzes->where('module.course_id', $enrollment->course_id);
            $courseAssignments = $assignments->where('module.course_id', $enrollment->course_id);

            $rows = collect();

            foreach ($courseQuizzes as $quiz) {
                $attempt = $bestAttempts->get($quiz->id);

                $rows->push([
                    'type' => 'Kuis',
                    'title' => $quiz->title,
                    'module' => $quiz->module->title,
                    'score' => $attempt?->score,
                    'max' => 100,
                    'status' => match (true) {
                        $attempt === null => 'Belum Dikerjakan',
                        // Esai belum dinilai — nilai yang tampil masih sementara
                        $attempt->needsReview() => 'Menunggu Penilaian',
                        $attempt->passed => 'Lulus',
                        default => 'Belum Lulus',
                    },
                ]);
            }

            foreach ($courseAssignments as $assignment) {
                $submission = $submissions->get($assignment->id);
                $submission?->setRelation('assignment', $assignment);

                $rows->push([
                    'type' => 'Tugas',
                    'title' => $assignment->title,
                    'module' => $assignment->module->title,
                    'score' => $submission?->score,
                    'max' => $assignment->max_score,
                    'status' => $submission === null
                        ? 'Belum Dikumpulkan'
                        : $submission->statusLabel(),
                ]);
            }

            return [
                'enrollment' => $enrollment,
                'rows' => $rows,
            ];
        });

        return view('grades.index', compact('report'));
    }
}
