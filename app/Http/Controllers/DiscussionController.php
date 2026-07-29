<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Discussion;
use App\Models\Lesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class DiscussionController extends Controller
{
    /**
     * Simpan pertanyaan baru atau balasan atas pertanyaan yang ada.
     */
    public function store(Request $request, Course $course, string $lesson)
    {
        [$lessonModel, $redirect] = $this->guard($course, $lesson);
        if ($redirect) {
            return $redirect;
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $parentId = null;
        if (! empty($validated['parent_id'])) {
            // Balasan hanya boleh menempel pada pertanyaan di materi yang sama,
            // dan tidak boleh bertingkat lebih dari satu level.
            $parent = Discussion::where('id', $validated['parent_id'])
                ->where('lesson_id', $lessonModel->id)
                ->whereNull('parent_id')
                ->first();

            abort_if($parent === null, 404);

            $parentId = $parent->id;
        }

        Discussion::create([
            'lesson_id' => $lessonModel->id,
            'user_id' => Auth::id(),
            'parent_id' => $parentId,
            'body' => $validated['body'],
        ]);

        return redirect()
            ->route('learn.show', [$course->slug, $lessonModel->slug])
            ->with('success', $parentId ? 'Balasan terkirim.' : 'Pertanyaan terkirim.');
    }

    /**
     * Hapus pertanyaan/balasan (penulisnya sendiri, pengajar kelas, atau Admin).
     */
    public function destroy(Course $course, Discussion $discussion)
    {
        Gate::authorize('delete', $discussion);

        $lessonSlug = $discussion->lesson->slug;
        $discussion->delete();

        return redirect()
            ->route('learn.show', [$course->slug, $lessonSlug])
            ->with('success', 'Diskusi dihapus.');
    }

    /**
     * Materi harus milik kursus pada URL dan siswa harus terdaftar.
     *
     * @return array{0: Lesson|null, 1: RedirectResponse|null}
     */
    private function guard(Course $course, string $lesson): array
    {
        $lessonModel = Lesson::where('slug', $lesson)
            ->whereHas('module', fn ($q) => $q->where('course_id', $course->id))
            ->first();

        abort_if($lessonModel === null, 404);

        $enrolled = $course->enrollments()
            ->where('user_id', Auth::id())
            ->whereIn('status', ['active', 'completed'])
            ->exists();

        // Pengajar kelas boleh ikut berdiskusi tanpa perlu mendaftar
        $isInstructor = $course->instructor_id === Auth::id();

        if (! $enrolled && ! $isInstructor) {
            return [null, redirect()
                ->route('courses.show', $course->slug)
                ->with('error', 'Kamu belum terdaftar di kelas ini.')];
        }

        return [$lessonModel, null];
    }
}
