<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningPath;
use Illuminate\Support\Facades\Auth;

class LearningPathController extends Controller
{
    /**
     * Katalog jalur belajar yang dipublikasikan.
     */
    public function index()
    {
        $paths = LearningPath::where('status', 'published')
            ->with('courses')
            ->orderBy('title')
            ->get();

        $joinedIds = Auth::check()
            ? Auth::user()->learningPaths()->pluck('learning_paths.id')
            : collect();

        return view('paths.index', compact('paths', 'joinedIds'));
    }

    /**
     * Detail jalur: urutan kursus beserta status terkunci / sedang berjalan / selesai.
     */
    public function show(LearningPath $path)
    {
        abort_unless($path->status === 'published', 404);

        $path->load('courses.instructor');
        $user = Auth::user();

        $joined = $user !== null && $path->isJoinedBy($user);

        $enrollments = $user !== null
            ? Enrollment::where('user_id', $user->id)
                ->whereIn('course_id', $path->courses->pluck('id'))
                ->get()
                ->keyBy('course_id')
            : collect();

        $steps = $path->courses->map(function (Course $course, int $index) use ($user, $path, $joined, $enrollments) {
            $enrollment = $enrollments->get($course->id);

            return [
                'number' => $index + 1,
                'course' => $course,
                'enrollment' => $enrollment,
                // Sebelum bergabung, seluruh kursus ditampilkan terbuka —
                // penguncian baru berlaku setelah siswa mengikuti jalur.
                'unlocked' => ! $joined || ($user !== null && $path->isCourseUnlockedFor($user, $course)),
                'completed' => $enrollment?->status === 'completed',
            ];
        });

        return view('paths.show', [
            'path' => $path,
            'steps' => $steps,
            'joined' => $joined,
            'progress' => $user !== null ? $path->progressFor($user) : 0,
        ]);
    }

    /**
     * Siswa bergabung ke jalur; sejak saat ini urutan prasyarat berlaku baginya.
     */
    public function join(LearningPath $path)
    {
        abort_unless($path->status === 'published', 404);

        Auth::user()->learningPaths()->syncWithoutDetaching([$path->id]);

        return redirect()
            ->route('paths.show', $path->slug)
            ->with('success', 'Kamu bergabung ke jalur ini. Mulai dari kursus pertama ya!');
    }

    /**
     * Keluar dari jalur — prasyarat tidak lagi mengunci kursus mana pun.
     * Progres kursus yang sudah dijalani tetap utuh.
     */
    public function leave(LearningPath $path)
    {
        Auth::user()->learningPaths()->detach($path->id);

        return redirect()
            ->route('paths.show', $path->slug)
            ->with('success', 'Kamu keluar dari jalur ini. Progres kursusmu tetap tersimpan.');
    }
}
