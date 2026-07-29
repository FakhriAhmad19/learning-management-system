<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Notifications\QuizNeedsReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuizController extends Controller
{
    /**
     * Toleransi keterlambatan pengiriman (detik) agar siswa jujur tidak dirugikan
     * oleh jeda jaringan saat pengiriman otomatis pada hitungan mundur nol.
     */
    private const GRACE_SECONDS = 30;

    /**
     * Tampilkan halaman pengerjaan kuis (beserta hasil percobaan terakhir bila ada).
     */
    public function show(Course $course, Quiz $quiz)
    {
        [$enrollment, $redirect] = $this->guard($course, $quiz);
        if ($redirect) {
            return $redirect;
        }

        $quiz->load(['questions.options', 'module']);
        $user = Auth::user();

        // Percobaan yang ditinggalkan sampai lewat waktu ditutup lebih dulu,
        // supaya siswa tidak tersangkut di percobaan yang jamnya sudah habis.
        $this->finalizeAbandonedAttempt($quiz, $user);

        $attempt = $quiz->hasTimeLimit()
            ? $this->startOrResumeAttempt($quiz, $user)
            : null;

        $lastAttempt = $quiz->lastCompletedAttemptFor($user);
        $lastAttempt?->load('answerGrades');
        $remainingAttempts = $quiz->remainingAttemptsFor($user);
        $canAttempt = $quiz->hasTimeLimit()
            ? $attempt !== null
            : $quiz->canBeAttemptedBy($user);

        return view('quiz.show', [
            'course' => $course,
            'quiz' => $quiz,
            'lastAttempt' => $lastAttempt,
            'remainingAttempts' => $remainingAttempts,
            'canAttempt' => $canAttempt,
            'deadline' => $attempt?->deadline(),
        ]);
    }

    /**
     * Nilai jawaban siswa secara otomatis lalu simpan percobaannya.
     */
    public function submit(Request $request, Course $course, Quiz $quiz)
    {
        [$enrollment, $redirect] = $this->guard($course, $quiz);
        if ($redirect) {
            return $redirect;
        }

        $user = Auth::user();

        if ($quiz->hasTimeLimit()) {
            $attempt = $quiz->inProgressAttemptFor($user);

            // Tidak ada percobaan berjalan: entah belum pernah membuka halaman
            // kuis, atau percobaannya sudah ditutup.
            if ($attempt === null) {
                if (! $quiz->canBeAttemptedBy($user)) {
                    return redirect()
                        ->route('quiz.show', [$course->slug, $quiz->id])
                        ->with('error', 'Kesempatan mengerjakan kuis ini sudah habis.');
                }

                $attempt = $this->startOrResumeAttempt($quiz, $user);
            }

            if ($this->isExpired($attempt)) {
                $this->markExpired($attempt);

                return redirect()
                    ->route('quiz.show', [$course->slug, $quiz->id])
                    ->with('error', 'Waktu pengerjaan habis. Percobaan ini dicatat dengan nilai 0.');
            }
        } else {
            if (! $quiz->canBeAttemptedBy($user)) {
                return redirect()
                    ->route('quiz.show', [$course->slug, $quiz->id])
                    ->with('error', 'Kesempatan mengerjakan kuis ini sudah habis.');
            }

            $attempt = $user->quizAttempts()->create([
                'quiz_id' => $quiz->id,
                'started_at' => now(),
            ]);
        }

        $attempt->update([
            'expired' => false,
            'answers' => $this->sanitizeAnswers($quiz, $request->input('answers', [])),
            'completed_at' => now(),
        ]);

        // Bagian beropsi dinilai sekarang; esai menunggu penilaian pengajar
        $attempt->recalculateScore();

        // Kelulusan kuis ikut memperbarui progres kursus
        $enrollment->recalculateProgress();

        if ($attempt->needsReview()) {
            $instructor = $quiz->module->course->instructor;
            $instructor?->notify(new QuizNeedsReview($attempt));

            return redirect()
                ->route('quiz.show', [$course->slug, $quiz->id])
                ->with('success', 'Jawaban terkirim. Ada soal esai yang menunggu penilaian pengajar.');
        }

        $score = $attempt->score;

        return redirect()
            ->route('quiz.show', [$course->slug, $quiz->id])
            ->with('success', $attempt->passed
                ? "Selamat! Kamu LULUS kuis dengan nilai {$score}."
                : "Nilai kamu {$score}. Belum mencapai batas lulus ({$quiz->passing_score}). Coba lagi ya!");
    }

    /**
     * Simpan jawaban sesuai tipe soalnya: id opsi untuk soal beropsi,
     * teks apa adanya untuk esai. Soal yang tidak dikenali dibuang.
     */
    private function sanitizeAnswers(Quiz $quiz, array $answers): array
    {
        $clean = [];

        foreach ($quiz->questions as $question) {
            $value = $answers[$question->id] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $clean[$question->id] = $question->isEssay()
                ? mb_substr((string) $value, 0, 10000)
                : (int) $value;
        }

        return $clean;
    }

    /**
     * Lanjutkan percobaan yang jamnya masih berjalan, atau mulai yang baru
     * bila jatah percobaan masih ada. Mengembalikan null bila jatah habis.
     */
    private function startOrResumeAttempt(Quiz $quiz, User $user): ?QuizAttempt
    {
        $existing = $quiz->inProgressAttemptFor($user);

        if ($existing !== null) {
            return $existing;
        }

        if (! $quiz->canBeAttemptedBy($user)) {
            return null;
        }

        return $user->quizAttempts()->create([
            'quiz_id' => $quiz->id,
            'started_at' => now(),
        ]);
    }

    /**
     * Tutup percobaan yang ditinggalkan melewati batas waktu, agar tercatat
     * sebagai percobaan terpakai dengan nilai 0.
     */
    private function finalizeAbandonedAttempt(Quiz $quiz, User $user): void
    {
        if (! $quiz->hasTimeLimit()) {
            return;
        }

        $attempt = $quiz->inProgressAttemptFor($user);

        if ($attempt !== null && $this->isExpired($attempt)) {
            $this->markExpired($attempt);
        }
    }

    private function markExpired(QuizAttempt $attempt): void
    {
        // Percobaan kedaluwarsa langsung final — tidak ada esai untuk dinilai
        $attempt->update([
            'score' => 0,
            'passed' => false,
            'expired' => true,
            'answers' => [],
            'completed_at' => now(),
            'graded_at' => now(),
        ]);
    }

    private function isExpired(QuizAttempt $attempt): bool
    {
        $deadline = $attempt->deadline();

        if ($deadline === null) {
            return false;
        }

        return now()->greaterThan($deadline->copy()->addSeconds(self::GRACE_SECONDS));
    }

    /**
     * Verifikasi akses: siswa harus terdaftar aktif/selesai dan kuis milik kursus ini.
     *
     * @return array{0: Enrollment|null, 1: RedirectResponse|null}
     */
    private function guard(Course $course, Quiz $quiz): array
    {
        // Kuis harus benar-benar milik kursus pada URL
        if ($quiz->module?->course_id !== $course->id) {
            abort(404);
        }

        $enrollment = $course->enrollments()
            ->where('user_id', Auth::id())
            ->whereIn('status', ['active', 'completed'])
            ->first();

        if (! $enrollment) {
            return [null, redirect()
                ->route('courses.show', $course->slug)
                ->with('error', 'Kamu belum terdaftar di kelas ini.')];
        }

        return [$enrollment, null];
    }
}
