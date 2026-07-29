<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Support\Facades\Auth;

class CertificateController extends Controller
{
    /**
     * Sertifikat kelulusan — hanya untuk siswa yang telah menyelesaikan
     * kursus 100% (enrollment berstatus "completed"). Halaman siap dicetak /
     * disimpan sebagai PDF lewat fitur cetak browser.
     */
    public function show(Course $course)
    {
        $user = Auth::user();

        $enrollment = $course->enrollments()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->first();

        if (! $enrollment) {
            return redirect()
                ->route('courses.show', $course->slug)
                ->with('error', 'Sertifikat baru tersedia setelah kamu menyelesaikan seluruh materi kelas ini.');
        }

        $course->load('instructor');

        return view('certificate.show', compact('course', 'enrollment', 'user'));
    }
}
