<?php

namespace Tests\Feature;

use App\Filament\Pages\Gradebook;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GradebookTest extends TestCase
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
     * Kursus berisi 1 materi, 1 kuis (1 soal), dan 1 tugas.
     *
     * @return array{course: Course, quiz: Quiz, assignment: Assignment, lesson: Lesson, correctOption: QuestionOption}
     */
    private function makeCourse(?User $instructor = null): array
    {
        $course = Course::create([
            'instructor_id' => ($instructor ?? $this->instructor())->id,
            'title' => 'Kursus Nilai',
            'about' => 'deskripsi',
            'price' => 0,
            'status' => 'published',
        ]);
        $module = Module::create(['course_id' => $course->id, 'title' => 'Modul 1', 'order' => 1]);
        $lesson = Lesson::create(['module_id' => $module->id, 'title' => 'Materi 1', 'content' => 'isi', 'order' => 1]);

        $quiz = Quiz::create(['module_id' => $module->id, 'title' => 'Kuis 1', 'passing_score' => 70]);
        $question = Question::create(['quiz_id' => $quiz->id, 'question' => '1+1?', 'order' => 1]);
        $correct = QuestionOption::create(['question_id' => $question->id, 'option_text' => '2', 'is_correct' => true]);
        QuestionOption::create(['question_id' => $question->id, 'option_text' => '3', 'is_correct' => false]);

        $assignment = Assignment::create([
            'module_id' => $module->id,
            'title' => 'Tugas 1',
            'max_score' => 100,
            'passing_score' => 60,
        ]);

        return compact('course', 'quiz', 'assignment', 'lesson') + ['correctOption' => $correct];
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

    // ---------- Sisi siswa: /my-grades ----------

    public function test_my_grades_requires_login(): void
    {
        $this->get(route('grades.index'))->assertRedirect(route('login'));
    }

    public function test_my_grades_shows_empty_state_without_enrollment(): void
    {
        $this->actingAs($this->student())
            ->get(route('grades.index'))
            ->assertOk()
            ->assertSee('Kamu belum mengikuti kelas apa pun');
    }

    public function test_my_grades_lists_quiz_and_assignment_rows(): void
    {
        $student = $this->student();
        ['course' => $course] = $this->makeCourse();
        $this->enroll($student, $course);

        $this->actingAs($student)
            ->get(route('grades.index'))
            ->assertOk()
            ->assertSee('Kuis 1')
            ->assertSee('Tugas 1')
            ->assertSee('Belum Dikerjakan')
            ->assertSee('Belum Dikumpulkan');
    }

    public function test_my_grades_shows_best_quiz_attempt(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz] = $this->makeCourse();
        $this->enroll($student, $course);

        QuizAttempt::create([
            'user_id' => $student->id, 'quiz_id' => $quiz->id,
            'score' => 40, 'passed' => false, 'answers' => [], 'completed_at' => now(),
        ]);
        QuizAttempt::create([
            'user_id' => $student->id, 'quiz_id' => $quiz->id,
            'score' => 90, 'passed' => true, 'answers' => [], 'completed_at' => now(),
        ]);

        $this->actingAs($student)
            ->get(route('grades.index'))
            ->assertOk()
            ->assertSee('90')
            ->assertSee('Lulus');
    }

    public function test_my_grades_shows_awaiting_grading_status(): void
    {
        $student = $this->student();
        ['course' => $course, 'assignment' => $assignment] = $this->makeCourse();
        $this->enroll($student, $course);

        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'content' => 'jawaban',
            'submitted_at' => now(),
        ]);

        $this->actingAs($student)
            ->get(route('grades.index'))
            ->assertOk()
            ->assertSee('Menunggu Penilaian');
    }

    public function test_my_grades_does_not_leak_other_students_scores(): void
    {
        $student = $this->student();
        $other = $this->student();
        ['course' => $course, 'quiz' => $quiz] = $this->makeCourse();
        $this->enroll($student, $course);
        $this->enroll($other, $course);

        QuizAttempt::create([
            'user_id' => $other->id, 'quiz_id' => $quiz->id,
            'score' => 95, 'passed' => true, 'answers' => [], 'completed_at' => now(),
        ]);

        $this->actingAs($student)
            ->get(route('grades.index'))
            ->assertOk()
            ->assertDontSee('95');
    }

    // ---------- Sisi pengajar: halaman Buku Nilai ----------

    public function test_student_cannot_access_gradebook_page(): void
    {
        $this->actingAs($this->student())->get('/admin/gradebook')->assertForbidden();
    }

    public function test_instructor_can_access_gradebook_page(): void
    {
        $this->actingAs($this->instructor())->get('/admin/gradebook')->assertOk();
    }

    public function test_gradebook_shows_enrolled_students_and_their_scores(): void
    {
        $instructor = $this->instructor();
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'assignment' => $assignment] = $this->makeCourse($instructor);
        $this->enroll($student, $course);

        QuizAttempt::create([
            'user_id' => $student->id, 'quiz_id' => $quiz->id,
            'score' => 80, 'passed' => true, 'answers' => [], 'completed_at' => now(),
        ]);
        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'content' => 'jawaban',
            'submitted_at' => now(),
            'score' => 70,
            'graded_at' => now(),
        ]);

        Livewire::actingAs($instructor)
            ->test(Gradebook::class)
            ->assertSee($student->name)
            ->assertSee('Kuis 1')
            ->assertSee('Tugas 1')
            ->assertSee('80')
            ->assertSee('70');
    }

    public function test_gradebook_flags_submissions_awaiting_grading(): void
    {
        $instructor = $this->instructor();
        $student = $this->student();
        ['course' => $course, 'assignment' => $assignment] = $this->makeCourse($instructor);
        $this->enroll($student, $course);

        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'content' => 'jawaban',
            'submitted_at' => now(),
        ]);

        Livewire::actingAs($instructor)
            ->test(Gradebook::class)
            ->assertSee('Perlu dinilai');
    }

    public function test_instructor_only_sees_own_courses_in_gradebook(): void
    {
        $owner = $this->instructor();
        $other = $this->instructor();

        $this->makeCourse($owner);
        ['course' => $otherCourse] = $this->makeCourse($other);
        $otherCourse->update(['title' => 'Kursus Instruktur Lain']);

        Livewire::actingAs($owner)
            ->test(Gradebook::class)
            ->assertDontSee('Kursus Instruktur Lain');
    }
}
