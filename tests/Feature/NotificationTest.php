<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Notifications\AssignmentGraded;
use App\Notifications\AssignmentPublished;
use App\Notifications\CourseCompleted;
use App\Notifications\SubmissionReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationTest extends TestCase
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

    /**
     * @return array{course: Course, module: Module, lesson: Lesson}
     */
    private function makeCourse(?User $instructor = null): array
    {
        $course = Course::create([
            'instructor_id' => ($instructor ?? $this->instructor())->id,
            'title' => 'Kursus Notifikasi',
            'about' => 'deskripsi',
            'price' => 0,
            'status' => 'published',
        ]);
        $module = Module::create(['course_id' => $course->id, 'title' => 'Modul 1', 'order' => 1]);
        $lesson = Lesson::create(['module_id' => $module->id, 'title' => 'Materi 1', 'content' => 'isi', 'order' => 1]);

        return compact('course', 'module', 'lesson');
    }

    private function makeAssignment(Module $module): Assignment
    {
        return Assignment::create([
            'module_id' => $module->id,
            'title' => 'Tugas 1',
            'max_score' => 100,
            'passing_score' => 60,
        ]);
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

    // ---------- Pemicu notifikasi ----------

    public function test_enrolled_students_are_notified_when_assignment_is_created(): void
    {
        Notification::fake();

        ['course' => $course, 'module' => $module] = $this->makeCourse();
        $enrolled = $this->student();
        $notEnrolled = $this->student();
        $this->enroll($enrolled, $course);

        $this->makeAssignment($module);

        Notification::assertSentTo($enrolled, AssignmentPublished::class);
        Notification::assertNotSentTo($notEnrolled, AssignmentPublished::class);
    }

    public function test_instructor_is_notified_when_submission_arrives(): void
    {
        Notification::fake();

        $instructor = $this->instructor();
        ['course' => $course, 'module' => $module] = $this->makeCourse($instructor);
        $assignment = $this->makeAssignment($module);
        $student = $this->student();
        $this->enroll($student, $course);

        $this->actingAs($student)->post(
            route('assignment.submit', [$course->slug, $assignment->id]),
            ['content' => 'jawaban']
        );

        Notification::assertSentTo($instructor, SubmissionReceived::class);
    }

    public function test_student_is_notified_when_assignment_is_graded(): void
    {
        Notification::fake();

        ['course' => $course, 'module' => $module] = $this->makeCourse();
        $assignment = $this->makeAssignment($module);
        $student = $this->student();
        $this->enroll($student, $course);

        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'content' => 'jawaban',
            'submitted_at' => now(),
        ]);

        Notification::assertNotSentTo($student, AssignmentGraded::class);

        $submission->update(['score' => 80, 'graded_at' => now()]);

        Notification::assertSentTo($student, AssignmentGraded::class);
    }

    public function test_grading_notification_is_sent_only_once(): void
    {
        Notification::fake();

        ['course' => $course, 'module' => $module] = $this->makeCourse();
        $assignment = $this->makeAssignment($module);
        $student = $this->student();
        $this->enroll($student, $course);

        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'content' => 'jawaban',
            'submitted_at' => now(),
        ]);

        $submission->update(['score' => 80, 'graded_at' => now()]);
        // Mengubah umpan balik saja tidak mengirim ulang notifikasi
        $submission->update(['feedback' => 'bagus']);

        Notification::assertSentToTimes($student, AssignmentGraded::class, 1);
    }

    public function test_student_is_notified_when_course_is_completed(): void
    {
        Notification::fake();

        ['course' => $course, 'lesson' => $lesson] = $this->makeCourse();
        $student = $this->student();
        $this->enroll($student, $course);

        $this->actingAs($student)->post(route('learn.complete', [$course->slug, $lesson->slug]));

        Notification::assertSentTo($student, CourseCompleted::class);
    }

    public function test_course_completed_notification_is_not_repeated(): void
    {
        Notification::fake();

        ['course' => $course, 'lesson' => $lesson] = $this->makeCourse();
        $student = $this->student();
        $enrollment = $this->enroll($student, $course);

        $this->actingAs($student)->post(route('learn.complete', [$course->slug, $lesson->slug]));
        $enrollment->fresh()->recalculateProgress();

        Notification::assertSentToTimes($student, CourseCompleted::class, 1);
    }

    // ---------- UI notifikasi siswa ----------

    public function test_notifications_page_lists_notifications(): void
    {
        ['course' => $course, 'module' => $module] = $this->makeCourse();
        $student = $this->student();
        $this->enroll($student, $course);
        $this->makeAssignment($module);

        $this->actingAs($student)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Tugas baru di '.$course->title)
            ->assertSee('Tugas 1');
    }

    public function test_notifications_page_shows_empty_state(): void
    {
        $this->actingAs($this->student())
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Belum ada notifikasi');
    }

    public function test_reading_a_notification_marks_it_read_and_redirects(): void
    {
        ['course' => $course, 'module' => $module] = $this->makeCourse();
        $student = $this->student();
        $this->enroll($student, $course);
        $assignment = $this->makeAssignment($module);

        $notification = $student->notifications()->firstOrFail();
        $this->assertNull($notification->read_at);

        $this->actingAs($student)
            ->get(route('notifications.read', $notification->id))
            ->assertRedirect(route('assignment.show', [$course->slug, $assignment->id]));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_student_cannot_read_another_students_notification(): void
    {
        ['course' => $course, 'module' => $module] = $this->makeCourse();
        $owner = $this->student();
        $intruder = $this->student();
        $this->enroll($owner, $course);
        $this->makeAssignment($module);

        $notification = $owner->notifications()->firstOrFail();

        $this->actingAs($intruder)
            ->get(route('notifications.read', $notification->id))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_as_read(): void
    {
        ['course' => $course, 'module' => $module] = $this->makeCourse();
        $student = $this->student();
        $this->enroll($student, $course);
        $this->makeAssignment($module);
        $this->makeAssignment($module);

        $this->assertSame(2, $student->unreadNotifications()->count());

        $this->actingAs($student)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $student->fresh()->unreadNotifications()->count());
    }

    public function test_unread_badge_appears_in_navigation(): void
    {
        ['course' => $course, 'module' => $module] = $this->makeCourse();
        $student = $this->student();
        $this->enroll($student, $course);
        $this->makeAssignment($module);

        $this->actingAs($student)
            ->get(route('my-courses'))
            ->assertOk()
            ->assertSee('Notifikasi');
    }

    // ---------- Isi email ----------

    public function test_graded_mail_contains_score_and_feedback(): void
    {
        ['course' => $course, 'module' => $module] = $this->makeCourse();
        $assignment = $this->makeAssignment($module);
        $student = $this->student();
        $this->enroll($student, $course);

        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'content' => 'jawaban',
            'submitted_at' => now(),
            'score' => 85,
            'feedback' => 'Kerja bagus',
            'graded_at' => now(),
        ]);

        $mail = (new AssignmentGraded($submission))->toMail($student);
        $rendered = $mail->render();

        $this->assertStringContainsString('85', $rendered);
        $this->assertStringContainsString('Kerja bagus', $rendered);
        $this->assertStringContainsString('LULUS', $rendered);
    }
}
