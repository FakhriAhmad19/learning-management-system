<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        Role::firstOrCreate(['name' => 'Student']);
        $user = User::factory()->create();
        $user->assignRole('Student');

        return $user;
    }

    /**
     * Kursus dengan 1 materi + 1 tugas → tiap unit bernilai 50% progres.
     *
     * @return array{0: Course, 1: Assignment, 2: Lesson}
     */
    private function makeCourseWithAssignment(): array
    {
        $course = Course::create([
            'instructor_id' => User::factory()->create()->id,
            'title' => 'Kursus Uji Tugas',
            'about' => 'deskripsi',
            'price' => 0,
            'status' => 'published',
        ]);
        $module = Module::create(['course_id' => $course->id, 'title' => 'Modul 1', 'order' => 1]);
        $lesson = Lesson::create(['module_id' => $module->id, 'title' => 'Materi 1', 'content' => 'isi', 'order' => 1]);
        $assignment = Assignment::create([
            'module_id' => $module->id,
            'title' => 'Tugas 1',
            'description' => 'kerjakan ini',
            'max_score' => 100,
            'passing_score' => 60,
        ]);

        return [$course, $assignment, $lesson];
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

    public function test_assignment_page_is_gated_to_enrolled_students(): void
    {
        $student = $this->student();
        [$course, $assignment] = $this->makeCourseWithAssignment();

        $this->actingAs($student)
            ->get(route('assignment.show', [$course->slug, $assignment->id]))
            ->assertRedirect(route('courses.show', $course->slug));
    }

    public function test_enrolled_student_can_see_assignment(): void
    {
        $student = $this->student();
        [$course, $assignment] = $this->makeCourseWithAssignment();
        $this->enroll($student, $course);

        $this->actingAs($student)
            ->get(route('assignment.show', [$course->slug, $assignment->id]))
            ->assertOk()
            ->assertSee('Tugas 1')
            ->assertSee('Kumpulkan Tugas');
    }

    public function test_student_can_submit_text_answer(): void
    {
        $student = $this->student();
        [$course, $assignment] = $this->makeCourseWithAssignment();
        $this->enroll($student, $course);

        $this->actingAs($student)
            ->post(route('assignment.submit', [$course->slug, $assignment->id]), [
                'content' => 'Ini jawaban saya',
            ])
            ->assertRedirect(route('assignment.show', [$course->slug, $assignment->id]));

        $this->assertDatabaseHas('assignment_submissions', [
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'content' => 'Ini jawaban saya',
            'graded_at' => null,
        ]);
    }

    public function test_student_can_submit_file_attachment(): void
    {
        Storage::fake('public');
        $student = $this->student();
        [$course, $assignment] = $this->makeCourseWithAssignment();
        $this->enroll($student, $course);

        $this->actingAs($student)
            ->post(route('assignment.submit', [$course->slug, $assignment->id]), [
                'attachment' => UploadedFile::fake()->create('tugas.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $submission = $assignment->submissions()->first();
        $this->assertNotNull($submission->attachment);
        Storage::disk('public')->assertExists($submission->attachment);
    }

    public function test_empty_submission_is_rejected(): void
    {
        $student = $this->student();
        [$course, $assignment] = $this->makeCourseWithAssignment();
        $this->enroll($student, $course);

        $this->actingAs($student)
            ->post(route('assignment.submit', [$course->slug, $assignment->id]), ['content' => ''])
            ->assertSessionHasErrors('content');

        $this->assertDatabaseCount('assignment_submissions', 0);
    }

    public function test_student_can_update_submission_before_grading(): void
    {
        $student = $this->student();
        [$course, $assignment] = $this->makeCourseWithAssignment();
        $this->enroll($student, $course);

        $this->actingAs($student)->post(route('assignment.submit', [$course->slug, $assignment->id]), ['content' => 'versi 1']);
        $this->actingAs($student)->post(route('assignment.submit', [$course->slug, $assignment->id]), ['content' => 'versi 2']);

        $this->assertDatabaseCount('assignment_submissions', 1);
        $this->assertSame('versi 2', $assignment->submissions()->first()->content);
    }

    public function test_graded_submission_cannot_be_changed(): void
    {
        $student = $this->student();
        [$course, $assignment] = $this->makeCourseWithAssignment();
        $this->enroll($student, $course);

        $this->actingAs($student)->post(route('assignment.submit', [$course->slug, $assignment->id]), ['content' => 'versi 1']);

        $assignment->submissions()->first()->update([
            'score' => 80,
            'graded_at' => now(),
        ]);

        $this->actingAs($student)
            ->post(route('assignment.submit', [$course->slug, $assignment->id]), ['content' => 'versi 2'])
            ->assertSessionHas('error');

        $this->assertSame('versi 1', $assignment->submissions()->first()->content);
    }

    public function test_submitting_alone_does_not_complete_the_assignment_unit(): void
    {
        $student = $this->student();
        [$course, $assignment, $lesson] = $this->makeCourseWithAssignment();
        $enrollment = $this->enroll($student, $course);

        // Selesaikan materi (1 dari 2 unit)
        $this->actingAs($student)->post(route('learn.complete', [$course->slug, $lesson->slug]));
        $this->assertSame(50, $enrollment->fresh()->progress_percentage);

        // Mengumpulkan saja belum menambah progres — masih menunggu penilaian
        $this->actingAs($student)->post(route('assignment.submit', [$course->slug, $assignment->id]), ['content' => 'jawaban']);
        $this->assertSame(50, $enrollment->fresh()->progress_percentage);
    }

    public function test_passing_grade_completes_the_course(): void
    {
        $student = $this->student();
        [$course, $assignment, $lesson] = $this->makeCourseWithAssignment();
        $enrollment = $this->enroll($student, $course);

        $this->actingAs($student)->post(route('learn.complete', [$course->slug, $lesson->slug]));
        $this->actingAs($student)->post(route('assignment.submit', [$course->slug, $assignment->id]), ['content' => 'jawaban']);

        // Pengajar memberi nilai lulus → progres jadi 100% & kursus selesai
        $assignment->submissions()->first()->update([
            'score' => 75,
            'graded_at' => now(),
        ]);

        $enrollment->refresh();
        $this->assertSame(100, $enrollment->progress_percentage);
        $this->assertSame('completed', $enrollment->status);
    }

    public function test_failing_grade_does_not_complete_the_course(): void
    {
        $student = $this->student();
        [$course, $assignment, $lesson] = $this->makeCourseWithAssignment();
        $enrollment = $this->enroll($student, $course);

        $this->actingAs($student)->post(route('learn.complete', [$course->slug, $lesson->slug]));
        $this->actingAs($student)->post(route('assignment.submit', [$course->slug, $assignment->id]), ['content' => 'jawaban']);

        $assignment->submissions()->first()->update([
            'score' => 40, // di bawah passing_score 60
            'graded_at' => now(),
        ]);

        $enrollment->refresh();
        $this->assertSame(50, $enrollment->progress_percentage);
        $this->assertSame('active', $enrollment->status);
    }

    public function test_certificate_is_blocked_until_assignment_is_passed(): void
    {
        $student = $this->student();
        [$course, $assignment, $lesson] = $this->makeCourseWithAssignment();
        $this->enroll($student, $course);

        $this->actingAs($student)->post(route('learn.complete', [$course->slug, $lesson->slug]));
        $this->actingAs($student)->post(route('assignment.submit', [$course->slug, $assignment->id]), ['content' => 'jawaban']);

        $this->actingAs($student)
            ->get(route('certificate.show', $course->slug))
            ->assertRedirect();

        $assignment->submissions()->first()->update(['score' => 90, 'graded_at' => now()]);

        $this->actingAs($student)
            ->get(route('certificate.show', $course->slug))
            ->assertOk();
    }
}
