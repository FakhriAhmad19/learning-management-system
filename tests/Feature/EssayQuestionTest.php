<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Notifications\QuizGraded;
use App\Notifications\QuizNeedsReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EssayQuestionTest extends TestCase
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
     * Kuis: 1 pilihan ganda (1 poin) + 1 esai (bobot bisa diatur).
     *
     * @return array{course: Course, quiz: Quiz, mc: Question, correct: QuestionOption, wrong: QuestionOption, essay: Question, lesson: Lesson}
     */
    private function makeQuiz(int $essayPoints = 1, int $passingScore = 70, ?User $instructor = null): array
    {
        $course = Course::create([
            'instructor_id' => ($instructor ?? $this->instructor())->id,
            'title' => 'Kursus Esai',
            'about' => 'deskripsi',
            'price' => 0,
            'status' => 'published',
        ]);
        $module = Module::create(['course_id' => $course->id, 'title' => 'Modul 1', 'order' => 1]);
        $lesson = Lesson::create(['module_id' => $module->id, 'title' => 'Materi 1', 'content' => 'isi', 'order' => 1]);

        $quiz = Quiz::create([
            'module_id' => $module->id,
            'title' => 'Kuis Esai',
            'passing_score' => $passingScore,
        ]);

        $mc = Question::create(['quiz_id' => $quiz->id, 'question' => '1+1?', 'order' => 1]);
        $correct = QuestionOption::create(['question_id' => $mc->id, 'option_text' => '2', 'is_correct' => true]);
        $wrong = QuestionOption::create(['question_id' => $mc->id, 'option_text' => '3', 'is_correct' => false]);

        $essay = Question::create([
            'quiz_id' => $quiz->id,
            'type' => Question::TYPE_ESSAY,
            'points' => $essayPoints,
            'question' => 'Jelaskan konsep MVC.',
            'order' => 2,
        ]);

        return compact('course', 'quiz', 'mc', 'correct', 'wrong', 'essay', 'lesson');
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

    private function submit(User $student, Course $course, Quiz $quiz, array $answers)
    {
        return $this->actingAs($student)->post(route('quiz.submit', [$course->slug, $quiz->id]), ['answers' => $answers]);
    }

    // ---------- Pengerjaan ----------

    public function test_essay_question_is_rendered_as_textarea(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz] = $this->makeQuiz();
        $this->enroll($student, $course);

        $this->actingAs($student)
            ->get(route('quiz.show', [$course->slug, $quiz->id]))
            ->assertOk()
            ->assertSee('Jelaskan konsep MVC.')
            ->assertSee('Esai')
            ->assertSee('Tulis jawaban kamu di sini');
    }

    public function test_essay_text_is_stored_and_option_answer_stays_numeric(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'mc' => $mc, 'correct' => $correct, 'essay' => $essay] = $this->makeQuiz();
        $this->enroll($student, $course);

        $this->submit($student, $course, $quiz, [
            $mc->id => $correct->id,
            $essay->id => 'MVC memisahkan model, view, dan controller.',
        ]);

        $attempt = $student->quizAttempts()->firstOrFail();

        $this->assertSame($correct->id, $attempt->answers[$mc->id]);
        $this->assertSame('MVC memisahkan model, view, dan controller.', $attempt->answers[$essay->id]);
        $this->assertSame('MVC memisahkan model, view, dan controller.', $attempt->essayAnswerFor($essay));
    }

    // ---------- Status menunggu penilaian ----------

    public function test_attempt_with_essay_awaits_review_and_is_not_passed(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'mc' => $mc, 'correct' => $correct, 'essay' => $essay] = $this->makeQuiz();
        $this->enroll($student, $course);

        $this->submit($student, $course, $quiz, [$mc->id => $correct->id, $essay->id => 'jawaban']);

        $attempt = $student->quizAttempts()->firstOrFail();

        $this->assertTrue($attempt->needsReview());
        $this->assertFalse($attempt->isFullyGraded());
        $this->assertFalse($attempt->passed);
        // Baru bagian pilihan ganda yang terhitung: 1 dari 2 poin
        $this->assertSame(50, $attempt->score);
    }

    public function test_quiz_without_essay_is_graded_immediately(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'mc' => $mc, 'correct' => $correct, 'essay' => $essay] = $this->makeQuiz();
        $essay->delete();
        $this->enroll($student, $course);

        $this->submit($student, $course, $quiz, [$mc->id => $correct->id]);

        $attempt = $student->quizAttempts()->firstOrFail();

        $this->assertFalse($attempt->needsReview());
        $this->assertTrue($attempt->isFullyGraded());
        $this->assertTrue($attempt->passed);
        $this->assertSame(100, $attempt->score);
    }

    public function test_attempt_on_essay_free_quiz_is_never_pending(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'essay' => $essay] = $this->makeQuiz();
        $essay->delete();
        $this->enroll($student, $course);

        // Baris dibuat langsung tanpa melewati recalculateScore(), meniru
        // data lama atau penulisan dari luar alur normal.
        $attempt = QuizAttempt::create([
            'user_id' => $student->id,
            'quiz_id' => $quiz->id,
            'score' => 90,
            'passed' => true,
            'answers' => [],
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $this->assertFalse($attempt->needsReview());
    }

    public function test_pending_attempt_does_not_advance_course_progress(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'mc' => $mc, 'correct' => $correct, 'essay' => $essay, 'lesson' => $lesson] = $this->makeQuiz();
        $enrollment = $this->enroll($student, $course);

        $this->actingAs($student)->post(route('learn.complete', [$course->slug, $lesson->slug]));
        $this->assertSame(50, $enrollment->fresh()->progress_percentage);

        $this->submit($student, $course, $quiz, [$mc->id => $correct->id, $essay->id => 'jawaban']);

        // Kuis belum lulus selama esai menunggu penilaian
        $this->assertSame(50, $enrollment->fresh()->progress_percentage);
    }

    public function test_student_sees_pending_state_on_quiz_page(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'mc' => $mc, 'correct' => $correct, 'essay' => $essay] = $this->makeQuiz();
        $this->enroll($student, $course);

        $this->submit($student, $course, $quiz, [$mc->id => $correct->id, $essay->id => 'jawaban saya']);

        $this->actingAs($student)
            ->get(route('quiz.show', [$course->slug, $quiz->id]))
            ->assertOk()
            ->assertSee('MENUNGGU PENILAIAN')
            ->assertSee('jawaban saya');
    }

    public function test_grades_page_shows_pending_status(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'mc' => $mc, 'correct' => $correct, 'essay' => $essay] = $this->makeQuiz();
        $this->enroll($student, $course);

        $this->submit($student, $course, $quiz, [$mc->id => $correct->id, $essay->id => 'jawaban']);

        $this->actingAs($student)
            ->get(route('grades.index'))
            ->assertOk()
            ->assertSee('Menunggu Penilaian');
    }

    // ---------- Penilaian & skor akhir ----------

    public function test_grading_the_essay_finalises_score_and_passes(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'mc' => $mc, 'correct' => $correct, 'essay' => $essay] = $this->makeQuiz();
        $this->enroll($student, $course);

        $this->submit($student, $course, $quiz, [$mc->id => $correct->id, $essay->id => 'jawaban']);
        $attempt = $student->quizAttempts()->firstOrFail();

        $attempt->answerGrades()->create(['question_id' => $essay->id, 'score' => 1]);
        $attempt->recalculateScore();

        $this->assertSame(100, $attempt->score);
        $this->assertTrue($attempt->passed);
        $this->assertTrue($attempt->isFullyGraded());
        $this->assertFalse($attempt->needsReview());
    }

    public function test_zero_essay_score_keeps_attempt_below_passing(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'mc' => $mc, 'correct' => $correct, 'essay' => $essay] = $this->makeQuiz();
        $this->enroll($student, $course);

        $this->submit($student, $course, $quiz, [$mc->id => $correct->id, $essay->id => 'jawaban']);
        $attempt = $student->quizAttempts()->firstOrFail();

        $attempt->answerGrades()->create(['question_id' => $essay->id, 'score' => 0]);
        $attempt->recalculateScore();

        $this->assertSame(50, $attempt->score);
        $this->assertFalse($attempt->passed);
        $this->assertTrue($attempt->isFullyGraded());
    }

    public function test_question_points_weight_the_final_score(): void
    {
        $student = $this->student();
        // Esai berbobot 3, pilihan ganda 1 → total 4 poin
        ['course' => $course, 'quiz' => $quiz, 'mc' => $mc, 'wrong' => $wrong, 'essay' => $essay] = $this->makeQuiz(essayPoints: 3);
        $this->enroll($student, $course);

        $this->submit($student, $course, $quiz, [$mc->id => $wrong->id, $essay->id => 'jawaban']);
        $attempt = $student->quizAttempts()->firstOrFail();

        $attempt->answerGrades()->create(['question_id' => $essay->id, 'score' => 3]);
        $attempt->recalculateScore();

        // 3 dari 4 poin = 75
        $this->assertSame(75, $attempt->score);
        $this->assertTrue($attempt->passed);
    }

    public function test_essay_score_is_capped_at_question_points(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'mc' => $mc, 'wrong' => $wrong, 'essay' => $essay] = $this->makeQuiz(essayPoints: 2);
        $this->enroll($student, $course);

        $this->submit($student, $course, $quiz, [$mc->id => $wrong->id, $essay->id => 'jawaban']);
        $attempt = $student->quizAttempts()->firstOrFail();

        // Nilai berlebih tidak boleh membuat skor melampaui bobot soal
        $attempt->answerGrades()->create(['question_id' => $essay->id, 'score' => 99]);
        $attempt->recalculateScore();

        // Maksimal 2 dari 3 poin
        $this->assertSame(67, $attempt->score);
    }

    public function test_grading_updates_course_progress(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'mc' => $mc, 'correct' => $correct, 'essay' => $essay, 'lesson' => $lesson] = $this->makeQuiz();
        $enrollment = $this->enroll($student, $course);

        $this->actingAs($student)->post(route('learn.complete', [$course->slug, $lesson->slug]));
        $this->submit($student, $course, $quiz, [$mc->id => $correct->id, $essay->id => 'jawaban']);

        $attempt = $student->quizAttempts()->firstOrFail();
        $attempt->answerGrades()->create(['question_id' => $essay->id, 'score' => 1]);
        $attempt->recalculateScore();
        $enrollment->fresh()->recalculateProgress();

        $enrollment->refresh();
        $this->assertSame(100, $enrollment->progress_percentage);
        $this->assertSame('completed', $enrollment->status);
    }

    // ---------- Notifikasi ----------

    public function test_instructor_is_notified_when_essay_needs_review(): void
    {
        Notification::fake();

        $instructor = $this->instructor();
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'mc' => $mc, 'correct' => $correct, 'essay' => $essay] = $this->makeQuiz(instructor: $instructor);
        $this->enroll($student, $course);

        $this->submit($student, $course, $quiz, [$mc->id => $correct->id, $essay->id => 'jawaban']);

        Notification::assertSentTo($instructor, QuizNeedsReview::class);
    }

    public function test_no_review_notification_for_auto_graded_quiz(): void
    {
        Notification::fake();

        $instructor = $this->instructor();
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'mc' => $mc, 'correct' => $correct, 'essay' => $essay] = $this->makeQuiz(instructor: $instructor);
        $essay->delete();
        $this->enroll($student, $course);

        $this->submit($student, $course, $quiz, [$mc->id => $correct->id]);

        Notification::assertNotSentTo($instructor, QuizNeedsReview::class);
    }

    public function test_graded_notification_mail_contains_final_score(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'mc' => $mc, 'correct' => $correct, 'essay' => $essay] = $this->makeQuiz();
        $this->enroll($student, $course);

        $this->submit($student, $course, $quiz, [$mc->id => $correct->id, $essay->id => 'jawaban']);
        $attempt = $student->quizAttempts()->firstOrFail();
        $attempt->answerGrades()->create(['question_id' => $essay->id, 'score' => 1]);
        $attempt->recalculateScore();

        $rendered = (new QuizGraded($attempt))->toMail($student)->render();

        $this->assertStringContainsString('100', $rendered);
        $this->assertStringContainsString('LULUS', $rendered);
    }

    // ---------- Panel penilaian ----------

    public function test_instructor_can_open_essay_grading_page(): void
    {
        $this->actingAs($this->instructor())->get('/admin/quiz-attempts')->assertOk();
    }

    public function test_student_cannot_open_essay_grading_page(): void
    {
        $this->actingAs($this->student())->get('/admin/quiz-attempts')->assertForbidden();
    }

    public function test_instructor_cannot_grade_another_instructors_attempt(): void
    {
        $owner = $this->instructor();
        $other = $this->instructor();
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'mc' => $mc, 'correct' => $correct, 'essay' => $essay] = $this->makeQuiz(instructor: $owner);
        $this->enroll($student, $course);

        $this->submit($student, $course, $quiz, [$mc->id => $correct->id, $essay->id => 'jawaban']);
        $attempt = QuizAttempt::firstOrFail();

        $this->assertTrue($owner->can('update', $attempt));
        $this->assertFalse($other->can('update', $attempt));
    }
}
