<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Notifications\PathCourseUnlocked;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LearningPathTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        Role::firstOrCreate(['name' => 'Student']);
        $user = User::factory()->create();
        $user->assignRole('Student');

        return $user;
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'Admin']);
        $user = User::factory()->create();
        $user->assignRole('Admin');

        return $user;
    }

    private function instructor(): User
    {
        Role::firstOrCreate(['name' => 'Instructor']);
        $user = User::factory()->create();
        $user->assignRole('Instructor');

        return $user;
    }

    /**
     * Kursus satu materi, supaya mudah dituntaskan dalam pengujian.
     *
     * @return array{course: Course, lesson: Lesson}
     */
    private function makeCourse(string $title): array
    {
        $course = Course::create([
            'instructor_id' => $this->instructor()->id,
            'title' => $title,
            'about' => 'deskripsi',
            'price' => 0,
            'status' => 'published',
        ]);
        $module = Module::create(['course_id' => $course->id, 'title' => 'Modul 1', 'order' => 1]);
        $lesson = Lesson::create(['module_id' => $module->id, 'title' => 'Materi 1', 'content' => 'isi', 'order' => 1]);

        return compact('course', 'lesson');
    }

    /**
     * Jalur berisi dua kursus berurutan.
     *
     * @return array{path: LearningPath, first: Course, second: Course, firstLesson: Lesson}
     */
    private function makePath(string $status = 'published'): array
    {
        ['course' => $first, 'lesson' => $firstLesson] = $this->makeCourse('Kursus Pertama');
        ['course' => $second] = $this->makeCourse('Kursus Kedua');

        $path = LearningPath::create([
            'title' => 'Jalur Uji',
            'description' => 'deskripsi jalur',
            'status' => $status,
        ]);
        $path->courses()->attach([
            $first->id => ['order' => 1],
            $second->id => ['order' => 2],
        ]);

        return compact('path', 'first', 'second', 'firstLesson');
    }

    private function completeCourse(User $student, Course $course, Lesson $lesson): void
    {
        Enrollment::firstOrCreate(
            ['user_id' => $student->id, 'course_id' => $course->id],
            ['amount_paid' => 0, 'status' => 'active', 'progress_percentage' => 0]
        );
        $this->actingAs($student)->post(route('learn.complete', [$course->slug, $lesson->slug]));
    }

    // ---------- Katalog & detail ----------

    public function test_path_catalog_lists_only_published_paths(): void
    {
        $this->makePath();
        LearningPath::create(['title' => 'Jalur Draft', 'status' => 'draft']);

        $this->get(route('paths.index'))
            ->assertOk()
            ->assertSee('Jalur Uji')
            ->assertDontSee('Jalur Draft');
    }

    public function test_draft_path_detail_is_not_found(): void
    {
        ['path' => $path] = $this->makePath('draft');

        $this->get(route('paths.show', $path->slug))->assertNotFound();
    }

    public function test_path_detail_shows_courses_in_order(): void
    {
        ['path' => $path] = $this->makePath();

        $response = $this->get(route('paths.show', $path->slug))->assertOk();

        $body = $response->getContent();
        $this->assertLessThan(
            strpos($body, 'Kursus Kedua'),
            strpos($body, 'Kursus Pertama'),
            'Kursus pertama harus tampil sebelum kursus kedua.'
        );
    }

    public function test_courses_are_not_locked_before_joining(): void
    {
        ['path' => $path] = $this->makePath();

        $this->actingAs($this->student())
            ->get(route('paths.show', $path->slug))
            ->assertOk()
            ->assertDontSee('Terkunci');
    }

    // ---------- Bergabung & penguncian ----------

    public function test_student_can_join_a_path(): void
    {
        $student = $this->student();
        ['path' => $path] = $this->makePath();

        $this->actingAs($student)
            ->post(route('paths.join', $path->id))
            ->assertRedirect(route('paths.show', $path->slug));

        $this->assertTrue($path->fresh()->isJoinedBy($student));
    }

    public function test_second_course_is_locked_after_joining(): void
    {
        $student = $this->student();
        ['path' => $path, 'first' => $first, 'second' => $second] = $this->makePath();
        $path->students()->attach($student->id);

        $this->assertTrue($path->isCourseUnlockedFor($student, $first));
        $this->assertFalse($path->isCourseUnlockedFor($student, $second));

        $this->actingAs($student)
            ->get(route('paths.show', $path->slug))
            ->assertOk()
            ->assertSee('Terkunci');
    }

    public function test_enrolling_in_a_locked_course_is_blocked(): void
    {
        $student = $this->student();
        ['path' => $path, 'second' => $second] = $this->makePath();
        $path->students()->attach($student->id);

        $this->actingAs($student)
            ->post(route('courses.enroll', $second->id))
            ->assertRedirect(route('paths.show', $path->slug));

        $this->assertDatabaseMissing('enrollments', [
            'user_id' => $student->id,
            'course_id' => $second->id,
            'status' => 'active',
        ]);
    }

    public function test_completing_the_first_course_unlocks_the_second(): void
    {
        $student = $this->student();
        ['path' => $path, 'first' => $first, 'second' => $second, 'firstLesson' => $lesson] = $this->makePath();
        $path->students()->attach($student->id);

        $this->completeCourse($student, $first, $lesson);

        $this->assertTrue($path->fresh()->isCourseUnlockedFor($student, $second));

        $this->actingAs($student)
            ->post(route('courses.enroll', $second->id))
            ->assertRedirect(route('learn.show', $second->slug));
    }

    public function test_gating_does_not_apply_to_paths_the_student_has_not_joined(): void
    {
        $student = $this->student();
        ['second' => $second] = $this->makePath();

        // Tidak bergabung ke jalur → kursus tetap bisa diambil dari katalog
        $this->actingAs($student)
            ->post(route('courses.enroll', $second->id))
            ->assertRedirect(route('learn.show', $second->slug));
    }

    public function test_leaving_a_path_removes_the_gate(): void
    {
        $student = $this->student();
        ['path' => $path, 'second' => $second] = $this->makePath();
        $path->students()->attach($student->id);

        $this->actingAs($student)->delete(route('paths.leave', $path->id));

        $this->assertFalse($path->fresh()->isJoinedBy($student));
        $this->actingAs($student)
            ->post(route('courses.enroll', $second->id))
            ->assertRedirect(route('learn.show', $second->slug));
    }

    public function test_already_enrolled_course_stays_accessible_even_if_locked(): void
    {
        $student = $this->student();
        ['path' => $path, 'second' => $second] = $this->makePath();

        // Terdaftar lebih dulu, baru kemudian bergabung ke jalur
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $second->id,
            'amount_paid' => 0,
            'status' => 'active',
            'progress_percentage' => 0,
        ]);
        $path->students()->attach($student->id);

        $this->actingAs($student)
            ->post(route('courses.enroll', $second->id))
            ->assertRedirect(route('learn.show', $second->slug));
    }

    public function test_completed_course_is_never_shown_as_locked(): void
    {
        $student = $this->student();
        ['path' => $path, 'second' => $second] = $this->makePath();

        // Selesaikan kursus kedua lebih dulu, baru bergabung ke jalur.
        // Prasyarat belum terpenuhi, tapi kursusnya jelas sudah selesai.
        $lesson = $second->lessons()->first();
        $this->completeCourse($student, $second, $lesson);
        $path->students()->attach($student->id);

        $this->assertFalse($path->fresh()->isCourseUnlockedFor($student, $second));

        $this->actingAs($student)
            ->get(route('paths.show', $path->slug))
            ->assertOk()
            ->assertSee('Selesai')
            ->assertDontSee('Terkunci — selesaikan kursus sebelumnya');
    }

    // ---------- Prasyarat & progres ----------

    public function test_unmet_prerequisites_are_listed(): void
    {
        $student = $this->student();
        ['path' => $path, 'first' => $first, 'second' => $second] = $this->makePath();

        $missing = $path->unmetPrerequisitesFor($student, $second);

        $this->assertCount(1, $missing);
        $this->assertSame($first->id, $missing->first()->id);
    }

    public function test_first_course_has_no_prerequisites(): void
    {
        $student = $this->student();
        ['path' => $path, 'first' => $first] = $this->makePath();

        $this->assertTrue($path->unmetPrerequisitesFor($student, $first)->isEmpty());
    }

    public function test_path_progress_reflects_completed_courses(): void
    {
        $student = $this->student();
        ['path' => $path, 'first' => $first, 'firstLesson' => $lesson] = $this->makePath();
        $path->students()->attach($student->id);

        $this->assertSame(0, $path->progressFor($student));

        $this->completeCourse($student, $first, $lesson);

        // 1 dari 2 kursus
        $this->assertSame(50, $path->fresh()->progressFor($student));
    }

    // ---------- Notifikasi ----------

    public function test_student_is_notified_when_next_course_unlocks(): void
    {
        Notification::fake();

        $student = $this->student();
        ['path' => $path, 'first' => $first, 'firstLesson' => $lesson] = $this->makePath();
        $path->students()->attach($student->id);

        $this->completeCourse($student, $first, $lesson);

        Notification::assertSentTo($student, PathCourseUnlocked::class);
    }

    public function test_no_unlock_notification_for_a_path_not_joined(): void
    {
        Notification::fake();

        $student = $this->student();
        ['first' => $first, 'firstLesson' => $lesson] = $this->makePath();

        $this->completeCourse($student, $first, $lesson);

        Notification::assertNotSentTo($student, PathCourseUnlocked::class);
    }

    public function test_no_unlock_notification_after_the_last_course(): void
    {
        Notification::fake();

        $student = $this->student();
        ['path' => $path, 'second' => $second] = $this->makePath();
        $path->students()->attach($student->id);

        $lastLesson = $second->lessons()->first();
        $this->completeCourse($student, $second, $lastLesson);

        Notification::assertNotSentTo($student, PathCourseUnlocked::class);
    }

    // ---------- Panel admin ----------

    public function test_only_admin_can_manage_learning_paths(): void
    {
        $this->actingAs($this->instructor())->get('/admin/learning-paths')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/learning-paths')->assertOk();
    }

    public function test_path_slug_is_generated_and_unique(): void
    {
        $first = LearningPath::create(['title' => 'Jalur Sama', 'status' => 'draft']);
        $second = LearningPath::create(['title' => 'Jalur Sama', 'status' => 'draft']);

        $this->assertSame('jalur-sama', $first->slug);
        $this->assertSame('jalur-sama-2', $second->slug);
    }
}
