<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningPath;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class EnrollmentController extends Controller
{
    /**
     * Proses pendaftaran siswa ke sebuah kursus.
     *
     * Seluruh kelas bersifat akses gratis: setiap pendaftaran langsung
     * berstatus "active" dan siswa diarahkan ke Ruang Belajar tanpa pembayaran.
     */
    public function store(Course $course)
    {
        // Hanya kursus terpublikasi yang bisa didaftari
        abort_unless($course->status === 'published', 404);

        $user = Auth::user();

        // Proteksi pendaftaran ganda: cek apakah sudah pernah terdaftar
        $enrollment = Enrollment::firstOrNew([
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);

        // Sudah terdaftar & bisa belajar → langsung ke Ruang Belajar
        if ($enrollment->exists && in_array($enrollment->status, ['active', 'completed'])) {
            return redirect()->route('learn.show', $course->slug);
        }

        // Prasyarat jalur belajar ditegakkan di sini, bukan hanya disembunyikan
        // di tampilan, supaya tidak bisa dilewati dengan menebak URL.
        if ($blocking = $this->blockingPath($user, $course)) {
            $missing = $blocking->unmetPrerequisitesFor($user, $course)->pluck('title')->implode(', ');

            return redirect()
                ->route('paths.show', $blocking->slug)
                ->with('error', 'Selesaikan dulu kursus sebelumnya di jalur ini: '.$missing.'.');
        }

        // Akses gratis: aktifkan langsung tanpa proses pembayaran
        $enrollment->amount_paid = 0;
        $enrollment->status = 'active';
        $enrollment->progress_percentage = $enrollment->progress_percentage ?? 0;
        $enrollment->save();

        return redirect()
            ->route('learn.show', $course->slug)
            ->with('success', 'Selamat! Kamu berhasil terdaftar. Selamat belajar.');
    }

    /**
     * Jalur yang diikuti siswa dan masih mengunci kursus ini, bila ada.
     *
     * Hanya jalur yang benar-benar diikuti yang mengunci — kursus yang sama
     * tetap bebas diambil dari katalog bila siswa tidak bergabung ke jalurnya.
     */
    private function blockingPath(User $user, Course $course): ?LearningPath
    {
        return $user->learningPaths()
            ->whereHas('courses', fn ($q) => $q->whereKey($course->id))
            ->with('courses')
            ->get()
            ->first(fn (LearningPath $path) => ! $path->isCourseUnlockedFor($user, $course));
    }
}
