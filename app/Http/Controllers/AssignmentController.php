<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssignmentController extends Controller
{
    /**
     * Halaman detail tugas: instruksi, tenggat, form pengumpulan, dan nilai
     * beserta umpan balik pengajar bila sudah dinilai.
     */
    public function show(Course $course, Assignment $assignment)
    {
        [$enrollment, $redirect] = $this->guard($course, $assignment);
        if ($redirect) {
            return $redirect;
        }

        $assignment->load('module');
        $submission = $assignment->submissionFor(Auth::user());

        return view('assignment.show', compact('course', 'assignment', 'submission'));
    }

    /**
     * Simpan (atau perbarui) pengumpulan tugas milik siswa.
     */
    public function submit(Request $request, Course $course, Assignment $assignment)
    {
        [$enrollment, $redirect] = $this->guard($course, $assignment);
        if ($redirect) {
            return $redirect;
        }

        $existing = $assignment->submissionFor(Auth::user());

        // Pengumpulan yang sudah dinilai tidak boleh diubah lagi
        if ($existing && $existing->isGraded()) {
            return redirect()
                ->route('assignment.show', [$course->slug, $assignment->id])
                ->with('error', 'Tugas ini sudah dinilai dan tidak dapat diubah lagi.');
        }

        $validated = $request->validate([
            'content' => ['nullable', 'string', 'max:20000'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,zip'],
        ]);

        // Minimal salah satu harus diisi
        if (blank($validated['content'] ?? null) && ! $request->hasFile('attachment')) {
            return back()
                ->withInput()
                ->withErrors(['content' => 'Isi jawaban atau unggah berkas tugas terlebih dahulu.']);
        }

        $attachmentPath = $existing?->attachment;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('assignment-submissions', 'public');
        }

        $assignment->submissions()->updateOrCreate(
            ['user_id' => Auth::id()],
            [
                'content' => $validated['content'] ?? null,
                'attachment' => $attachmentPath,
                'submitted_at' => now(),
            ]
        );

        return redirect()
            ->route('assignment.show', [$course->slug, $assignment->id])
            ->with('success', 'Tugas berhasil dikumpulkan. Menunggu penilaian pengajar.');
    }

    /**
     * Verifikasi akses: siswa harus terdaftar aktif/selesai dan tugas milik kursus ini.
     *
     * @return array{0: Enrollment|null, 1: RedirectResponse|null}
     */
    private function guard(Course $course, Assignment $assignment): array
    {
        if ($assignment->module?->course_id !== $course->id) {
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
