<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Discussion;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Notifications\DiscussionReplied;
use App\Notifications\QuestionAsked;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DiscussionTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        Role::firstOrCreate(['name' => 'Student']);
        $user = User::factory()->create();
        $user->assignRole('Student');

        return $user;
    }

    private function instructor(): User
    {
        Role::firstOrCreate(['name' => 'Instructor']);
        $user = User::factory()->create();
        $user->assignRole('Instructor');

        return $user;
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'Admin']);
        $user = User::factory()->create();
        $user->assignRole('Admin');

        return $user;
    }

    /**
     * @return array{course: Course, lesson: Lesson, instructor: User}
     */
    private function makeCourse(?User $instructor = null): array
    {
        $instructor ??= $this->instructor();

        $course = Course::create([
            'instructor_id' => $instructor->id,
            'title' => 'Kursus Diskusi',
            'about' => 'deskripsi',
            'price' => 0,
            'status' => 'published',
        ]);
        $module = Module::create(['course_id' => $course->id, 'title' => 'Modul 1', 'order' => 1]);
        $lesson = Lesson::create(['module_id' => $module->id, 'title' => 'Materi 1', 'content' => 'isi', 'order' => 1]);

        return compact('course', 'lesson', 'instructor');
    }

    private function enroll(User $student, Course $course): Enrollment
    {
        return Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'amount_paid' => 0,
            'status' => 'active',
            'progress_percentage' => 0,
        ]);
    }

    private function ask(User $user, Course $course, Lesson $lesson, string $body = 'Pertanyaan saya', ?int $parentId = null)
    {
        return $this->actingAs($user)->post(
            route('discussions.store', [$course->slug, $lesson->slug]),
            array_filter(['body' => $body, 'parent_id' => $parentId])
        );
    }

    // ---------- Akses ----------

    public function test_non_enrolled_student_cannot_post(): void
    {
        ['course' => $course, 'lesson' => $lesson] = $this->makeCourse();

        $this->ask($this->student(), $course, $lesson)
            ->assertRedirect(route('courses.show', $course->slug));

        $this->assertDatabaseCount('discussions', 0);
    }

    public function test_enrolled_student_can_ask_question(): void
    {
        ['course' => $course, 'lesson' => $lesson] = $this->makeCourse();
        $student = $this->student();
        $this->enroll($student, $course);

        $this->ask($student, $course, $lesson, 'Kenapa harus pakai MVC?')
            ->assertRedirect(route('learn.show', [$course->slug, $lesson->slug]));

        $this->assertDatabaseHas('discussions', [
            'lesson_id' => $lesson->id,
            'user_id' => $student->id,
            'parent_id' => null,
            'body' => 'Kenapa harus pakai MVC?',
        ]);
    }

    public function test_instructor_can_post_without_enrolling(): void
    {
        ['course' => $course, 'lesson' => $lesson, 'instructor' => $instructor] = $this->makeCourse();

        $this->ask($instructor, $course, $lesson, 'Silakan bertanya di sini')
            ->assertRedirect(route('learn.show', [$course->slug, $lesson->slug]));

        $this->assertDatabaseCount('discussions', 1);
    }

    public function test_empty_body_is_rejected(): void
    {
        ['course' => $course, 'lesson' => $lesson] = $this->makeCourse();
        $student = $this->student();
        $this->enroll($student, $course);

        $this->actingAs($student)
            ->post(route('discussions.store', [$course->slug, $lesson->slug]), ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('discussions', 0);
    }

    // ---------- Balasan ----------

    public function test_reply_is_attached_to_the_question(): void
    {
        ['course' => $course, 'lesson' => $lesson, 'instructor' => $instructor] = $this->makeCourse();
        $student = $this->student();
        $this->enroll($student, $course);

        $this->ask($student, $course, $lesson);
        $question = Discussion::firstOrFail();

        $this->ask($instructor, $course, $lesson, 'Ini jawabannya', $question->id);

        $reply = Discussion::where('parent_id', $question->id)->firstOrFail();
        $this->assertSame('Ini jawabannya', $reply->body);
        $this->assertTrue($reply->isReply());
        $this->assertSame(1, $question->replies()->count());
    }

    public function test_replies_cannot_nest_more_than_one_level(): void
    {
        ['course' => $course, 'lesson' => $lesson] = $this->makeCourse();
        $student = $this->student();
        $this->enroll($student, $course);

        $this->ask($student, $course, $lesson);
        $question = Discussion::firstOrFail();
        $this->ask($student, $course, $lesson, 'balasan', $question->id);
        $reply = Discussion::where('parent_id', $question->id)->firstOrFail();

        // Membalas sebuah balasan harus ditolak
        $this->ask($student, $course, $lesson, 'balasan atas balasan', $reply->id)
            ->assertNotFound();
    }

    public function test_reply_must_belong_to_the_same_lesson(): void
    {
        ['course' => $course, 'lesson' => $lesson] = $this->makeCourse();
        $otherLesson = Lesson::create([
            'module_id' => $lesson->module_id,
            'title' => 'Materi 2',
            'content' => 'isi',
            'order' => 2,
        ]);
        $student = $this->student();
        $this->enroll($student, $course);

        $this->ask($student, $course, $lesson);
        $question = Discussion::firstOrFail();

        $this->ask($student, $course, $otherLesson, 'nyasar', $question->id)
            ->assertNotFound();
    }

    public function test_instructor_answer_is_flagged(): void
    {
        ['course' => $course, 'lesson' => $lesson, 'instructor' => $instructor] = $this->makeCourse();
        $student = $this->student();
        $this->enroll($student, $course);

        $this->ask($student, $course, $lesson);
        $question = Discussion::firstOrFail();
        $this->ask($instructor, $course, $lesson, 'jawaban', $question->id);

        $reply = Discussion::where('parent_id', $question->id)->firstOrFail();

        $this->assertTrue($reply->isFromInstructor());
        $this->assertFalse($question->isFromInstructor());
    }

    // ---------- Tampilan di player ----------

    public function test_discussions_are_visible_in_the_player(): void
    {
        ['course' => $course, 'lesson' => $lesson, 'instructor' => $instructor] = $this->makeCourse();
        $student = $this->student();
        $this->enroll($student, $course);

        $this->ask($student, $course, $lesson, 'Kenapa harus pakai MVC?');
        $question = Discussion::firstOrFail();
        $this->ask($instructor, $course, $lesson, 'Karena memisahkan tanggung jawab', $question->id);

        $this->actingAs($student)
            ->get(route('learn.show', [$course->slug, $lesson->slug]))
            ->assertOk()
            ->assertSee('Kenapa harus pakai MVC?')
            ->assertSee('Karena memisahkan tanggung jawab')
            ->assertSee('Pengajar');
    }

    public function test_player_shows_empty_state_without_discussions(): void
    {
        ['course' => $course, 'lesson' => $lesson] = $this->makeCourse();
        $student = $this->student();
        $this->enroll($student, $course);

        $this->actingAs($student)
            ->get(route('learn.show', [$course->slug, $lesson->slug]))
            ->assertOk()
            ->assertSee('Belum ada pertanyaan');
    }

    // ---------- Hapus ----------

    public function test_author_can_delete_own_question(): void
    {
        ['course' => $course, 'lesson' => $lesson] = $this->makeCourse();
        $student = $this->student();
        $this->enroll($student, $course);
        $this->ask($student, $course, $lesson);
        $question = Discussion::firstOrFail();

        $this->actingAs($student)
            ->delete(route('discussions.destroy', [$course->slug, $question->id]))
            ->assertRedirect();

        $this->assertDatabaseCount('discussions', 0);
    }

    public function test_deleting_question_removes_its_replies(): void
    {
        ['course' => $course, 'lesson' => $lesson, 'instructor' => $instructor] = $this->makeCourse();
        $student = $this->student();
        $this->enroll($student, $course);
        $this->ask($student, $course, $lesson);
        $question = Discussion::firstOrFail();
        $this->ask($instructor, $course, $lesson, 'jawaban', $question->id);

        $this->assertDatabaseCount('discussions', 2);

        $this->actingAs($student)->delete(route('discussions.destroy', [$course->slug, $question->id]));

        $this->assertDatabaseCount('discussions', 0);
    }

    public function test_other_student_cannot_delete(): void
    {
        ['course' => $course, 'lesson' => $lesson] = $this->makeCourse();
        $owner = $this->student();
        $intruder = $this->student();
        $this->enroll($owner, $course);
        $this->enroll($intruder, $course);
        $this->ask($owner, $course, $lesson);
        $question = Discussion::firstOrFail();

        $this->actingAs($intruder)
            ->delete(route('discussions.destroy', [$course->slug, $question->id]))
            ->assertForbidden();

        $this->assertDatabaseCount('discussions', 1);
    }

    public function test_instructor_can_moderate_student_question(): void
    {
        ['course' => $course, 'lesson' => $lesson, 'instructor' => $instructor] = $this->makeCourse();
        $student = $this->student();
        $this->enroll($student, $course);
        $this->ask($student, $course, $lesson);
        $question = Discussion::firstOrFail();

        $this->actingAs($instructor)
            ->delete(route('discussions.destroy', [$course->slug, $question->id]))
            ->assertRedirect();

        $this->assertDatabaseCount('discussions', 0);
    }

    public function test_instructor_of_another_course_cannot_delete(): void
    {
        ['course' => $course, 'lesson' => $lesson] = $this->makeCourse();
        $outsider = $this->instructor();
        $student = $this->student();
        $this->enroll($student, $course);
        $this->ask($student, $course, $lesson);
        $question = Discussion::firstOrFail();

        $this->assertFalse($outsider->can('delete', $question));
    }

    public function test_admin_can_delete_anything(): void
    {
        ['course' => $course, 'lesson' => $lesson] = $this->makeCourse();
        $student = $this->student();
        $this->enroll($student, $course);
        $this->ask($student, $course, $lesson);
        $question = Discussion::firstOrFail();

        $this->assertTrue($this->admin()->can('delete', $question));
    }

    // ---------- Notifikasi ----------

    public function test_instructor_is_notified_of_new_question(): void
    {
        Notification::fake();

        ['course' => $course, 'lesson' => $lesson, 'instructor' => $instructor] = $this->makeCourse();
        $student = $this->student();
        $this->enroll($student, $course);

        $this->ask($student, $course, $lesson);

        Notification::assertSentTo($instructor, QuestionAsked::class);
    }

    public function test_instructor_is_not_notified_of_own_question(): void
    {
        Notification::fake();

        ['course' => $course, 'lesson' => $lesson, 'instructor' => $instructor] = $this->makeCourse();

        $this->ask($instructor, $course, $lesson);

        Notification::assertNotSentTo($instructor, QuestionAsked::class);
    }

    public function test_asker_is_notified_when_someone_replies(): void
    {
        Notification::fake();

        ['course' => $course, 'lesson' => $lesson, 'instructor' => $instructor] = $this->makeCourse();
        $student = $this->student();
        $this->enroll($student, $course);

        $this->ask($student, $course, $lesson);
        $question = Discussion::firstOrFail();
        $this->ask($instructor, $course, $lesson, 'jawaban', $question->id);

        Notification::assertSentTo($student, DiscussionReplied::class);
    }

    public function test_replying_to_own_question_sends_no_notification(): void
    {
        Notification::fake();

        ['course' => $course, 'lesson' => $lesson] = $this->makeCourse();
        $student = $this->student();
        $this->enroll($student, $course);

        $this->ask($student, $course, $lesson);
        $question = Discussion::firstOrFail();
        $this->ask($student, $course, $lesson, 'tambahan info', $question->id);

        Notification::assertNotSentTo($student, DiscussionReplied::class);
    }
}
