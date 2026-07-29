<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentFlowTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        Role::firstOrCreate(['name' => 'Student']);
        $user = User::factory()->create();
        $user->assignRole('Student');

        return $user;
    }

    private function makeCourse(float $price = 0): Course
    {
        $instructor = User::factory()->create();
        $course = Course::create([
            'instructor_id' => $instructor->id,
            'title' => 'Kursus Uji',
            'about' => 'deskripsi',
            'price' => $price,
            'status' => 'published',
        ]);
        $module = Module::create(['course_id' => $course->id, 'title' => 'Modul 1', 'order' => 1]);
        Lesson::create(['module_id' => $module->id, 'title' => 'Materi 1', 'content' => 'isi', 'order' => 1]);
        Lesson::create(['module_id' => $module->id, 'title' => 'Materi 2', 'content' => 'isi', 'order' => 2]);

        return $course;
    }

    public function test_my_courses_and_profile_pages_render(): void
    {
        $student = $this->student();

        $this->actingAs($student)->get('/my-courses')->assertOk();
        $this->actingAs($student)->get('/profile')->assertOk();
    }

    public function test_free_enrollment_is_instant_and_redirects_to_player(): void
    {
        $student = $this->student();
        $course = $this->makeCourse(price: 0);

        $this->actingAs($student)
            ->post("/courses/{$course->id}/enroll")
            ->assertRedirect(route('learn.show', $course->slug));

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
    }

    public function test_marking_all_lessons_complete_sets_progress_to_100_and_completed(): void
    {
        $student = $this->student();
        $course = $this->makeCourse();
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'amount_paid' => 0,
            'progress_percentage' => 0,
        ]);

        $lessons = $course->modules->flatMap->lessons;
        foreach ($lessons as $lesson) {
            $this->actingAs($student)->post("/learn/{$course->slug}/{$lesson->slug}/complete");
        }

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'progress_percentage' => 100,
            'status' => 'completed',
        ]);
    }

    public function test_player_is_blocked_without_active_enrollment(): void
    {
        $student = $this->student();
        $course = $this->makeCourse();

        $this->actingAs($student)
            ->get(route('learn.show', $course->slug))
            ->assertRedirect(route('courses.show', $course->slug));
    }

    public function test_certificate_requires_completed_enrollment(): void
    {
        $student = $this->student();
        $course = $this->makeCourse();

        // Belum selesai -> ditolak
        Enrollment::create([
            'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'amount_paid' => 0, 'progress_percentage' => 50,
        ]);
        $this->actingAs($student)
            ->get(route('certificate.show', $course->slug))
            ->assertRedirect(route('courses.show', $course->slug));

        // Setelah selesai -> bisa dilihat
        Enrollment::where('user_id', $student->id)->where('course_id', $course->id)
            ->update(['status' => 'completed', 'progress_percentage' => 100]);
        $this->actingAs($student)
            ->get(route('certificate.show', $course->slug))
            ->assertOk()
            ->assertSee($student->name);
    }

    private function makeQuiz(Course $course): Quiz
    {
        $module = $course->modules->first();
        $quiz = Quiz::create(['module_id' => $module->id, 'title' => 'Kuis', 'passing_score' => 70]);

        $q1 = Question::create(['quiz_id' => $quiz->id, 'question' => 'Q1', 'order' => 1]);
        QuestionOption::create(['question_id' => $q1->id, 'option_text' => 'Benar', 'is_correct' => true]);
        QuestionOption::create(['question_id' => $q1->id, 'option_text' => 'Salah', 'is_correct' => false]);

        $q2 = Question::create(['quiz_id' => $quiz->id, 'question' => 'Q2', 'order' => 2]);
        QuestionOption::create(['question_id' => $q2->id, 'option_text' => 'Benar', 'is_correct' => true]);
        QuestionOption::create(['question_id' => $q2->id, 'option_text' => 'Salah', 'is_correct' => false]);

        return $quiz->load('questions.options');
    }

    public function test_quiz_grading_all_correct_passes(): void
    {
        $student = $this->student();
        $course = $this->makeCourse();
        Enrollment::create([
            'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'amount_paid' => 0, 'progress_percentage' => 0,
        ]);
        $quiz = $this->makeQuiz($course);

        $answers = [];
        foreach ($quiz->questions as $q) {
            $answers[$q->id] = $q->options->firstWhere('is_correct', true)->id;
        }

        $this->actingAs($student)
            ->post(route('quiz.submit', [$course->slug, $quiz->id]), ['answers' => $answers])
            ->assertRedirect(route('quiz.show', [$course->slug, $quiz->id]));

        $this->assertDatabaseHas('quiz_attempts', [
            'user_id' => $student->id, 'quiz_id' => $quiz->id, 'score' => 100, 'passed' => true,
        ]);
    }

    public function test_quiz_grading_all_wrong_fails(): void
    {
        $student = $this->student();
        $course = $this->makeCourse();
        Enrollment::create([
            'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'amount_paid' => 0, 'progress_percentage' => 0,
        ]);
        $quiz = $this->makeQuiz($course);

        $answers = [];
        foreach ($quiz->questions as $q) {
            $answers[$q->id] = $q->options->firstWhere('is_correct', false)->id;
        }

        $this->actingAs($student)
            ->post(route('quiz.submit', [$course->slug, $quiz->id]), ['answers' => $answers]);

        $this->assertDatabaseHas('quiz_attempts', [
            'user_id' => $student->id, 'quiz_id' => $quiz->id, 'score' => 0, 'passed' => false,
        ]);
    }

    public function test_quiz_blocked_without_enrollment(): void
    {
        $student = $this->student();
        $course = $this->makeCourse();
        $quiz = $this->makeQuiz($course);

        $this->actingAs($student)
            ->get(route('quiz.show', [$course->slug, $quiz->id]))
            ->assertRedirect(route('courses.show', $course->slug));
    }

    public function test_course_completion_requires_lessons_done_and_quiz_passed(): void
    {
        $student = $this->student();
        $course = $this->makeCourse();
        $enrollment = Enrollment::create([
            'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'amount_paid' => 0, 'progress_percentage' => 0,
        ]);
        $quiz = $this->makeQuiz($course); // total unit = 2 materi + 1 kuis

        // Selesaikan semua materi, TAPI belum lulus kuis
        foreach ($course->modules->flatMap->lessons as $lesson) {
            $this->actingAs($student)->post("/learn/{$course->slug}/{$lesson->slug}/complete");
        }
        $enrollment->refresh();
        $this->assertLessThan(100, $enrollment->progress_percentage); // 2/3 = 67%
        $this->assertSame('active', $enrollment->status);

        // Lulus kuis -> baru 100% & completed
        $answers = [];
        foreach ($quiz->questions as $q) {
            $answers[$q->id] = $q->options->firstWhere('is_correct', true)->id;
        }
        $this->actingAs($student)->post(route('quiz.submit', [$course->slug, $quiz->id]), ['answers' => $answers]);

        $enrollment->refresh();
        $this->assertSame(100, $enrollment->progress_percentage);
        $this->assertSame('completed', $enrollment->status);
    }
}
