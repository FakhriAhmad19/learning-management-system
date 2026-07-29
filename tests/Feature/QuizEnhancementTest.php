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

class QuizEnhancementTest extends TestCase
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
     * @return array{course: Course, quiz: Quiz, correct: QuestionOption, wrong: QuestionOption, question: Question}
     */
    private function makeQuiz(array $quizAttributes = []): array
    {
        $course = Course::create([
            'instructor_id' => User::factory()->create()->id,
            'title' => 'Kursus Kuis',
            'about' => 'deskripsi',
            'price' => 0,
            'status' => 'published',
        ]);
        $module = Module::create(['course_id' => $course->id, 'title' => 'Modul 1', 'order' => 1]);
        Lesson::create(['module_id' => $module->id, 'title' => 'Materi 1', 'content' => 'isi', 'order' => 1]);

        $quiz = Quiz::create(array_merge([
            'module_id' => $module->id,
            'title' => 'Kuis 1',
            'passing_score' => 70,
        ], $quizAttributes));

        $question = Question::create(['quiz_id' => $quiz->id, 'question' => '1+1?', 'order' => 1]);
        $correct = QuestionOption::create(['question_id' => $question->id, 'option_text' => '2', 'is_correct' => true]);
        $wrong = QuestionOption::create(['question_id' => $question->id, 'option_text' => '3', 'is_correct' => false]);

        return compact('course', 'quiz', 'correct', 'wrong', 'question');
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

    // ---------- Batas percobaan ----------

    public function test_unlimited_attempts_by_default(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'wrong' => $wrong] = $this->makeQuiz();
        $this->enroll($student, $course);

        foreach (range(1, 5) as $i) {
            $this->actingAs($student)->post(
                route('quiz.submit', [$course->slug, $quiz->id]),
                ['answers' => [$quiz->questions->first()->id => $wrong->id]]
            );
        }

        $this->assertSame(5, $quiz->attemptsUsedBy($student));
        $this->assertNull($quiz->remainingAttemptsFor($student));
        $this->assertTrue($quiz->canBeAttemptedBy($student));
    }

    public function test_attempts_are_blocked_once_limit_is_reached(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'question' => $question, 'wrong' => $wrong] = $this->makeQuiz(['max_attempts' => 2]);
        $this->enroll($student, $course);

        $this->actingAs($student)->post(route('quiz.submit', [$course->slug, $quiz->id]), ['answers' => [$question->id => $wrong->id]]);
        $this->actingAs($student)->post(route('quiz.submit', [$course->slug, $quiz->id]), ['answers' => [$question->id => $wrong->id]]);

        $this->assertSame(0, $quiz->remainingAttemptsFor($student));

        $this->actingAs($student)
            ->post(route('quiz.submit', [$course->slug, $quiz->id]), ['answers' => [$question->id => $wrong->id]])
            ->assertSessionHas('error');

        // Percobaan ketiga tidak tercatat
        $this->assertSame(2, $quiz->attemptsUsedBy($student));
    }

    public function test_quiz_page_shows_locked_state_when_attempts_exhausted(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'question' => $question, 'wrong' => $wrong] = $this->makeQuiz(['max_attempts' => 1]);
        $this->enroll($student, $course);

        $this->actingAs($student)->post(route('quiz.submit', [$course->slug, $quiz->id]), ['answers' => [$question->id => $wrong->id]]);

        $this->actingAs($student)
            ->get(route('quiz.show', [$course->slug, $quiz->id]))
            ->assertOk()
            ->assertSee('Kesempatan mengerjakan sudah habis')
            ->assertDontSee('Kirim Jawaban');
    }

    public function test_passing_still_allows_retry_while_attempts_remain(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'question' => $question, 'correct' => $correct] = $this->makeQuiz(['max_attempts' => 3]);
        $this->enroll($student, $course);

        $this->actingAs($student)->post(route('quiz.submit', [$course->slug, $quiz->id]), ['answers' => [$question->id => $correct->id]]);

        $this->actingAs($student)
            ->get(route('quiz.show', [$course->slug, $quiz->id]))
            ->assertOk()
            ->assertSee('Kirim Jawaban');
    }

    // ---------- Batas waktu ----------

    public function test_quiz_page_exposes_deadline_when_time_limit_set(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz] = $this->makeQuiz(['time_limit_minutes' => 10]);
        $this->enroll($student, $course);

        $this->actingAs($student)
            ->get(route('quiz.show', [$course->slug, $quiz->id]))
            ->assertOk()
            ->assertSee('quiz-timer')
            ->assertSee('Waktu pengerjaan: 10 menit');
    }

    public function test_no_timer_when_time_limit_is_null(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz] = $this->makeQuiz();
        $this->enroll($student, $course);

        $this->actingAs($student)
            ->get(route('quiz.show', [$course->slug, $quiz->id]))
            ->assertOk()
            ->assertDontSee('quiz-timer');
    }

    public function test_submission_after_time_limit_scores_zero(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'question' => $question, 'correct' => $correct] = $this->makeQuiz(['time_limit_minutes' => 5]);
        $this->enroll($student, $course);

        // Membuka kuis menyimpan waktu mulai di sesi
        $this->actingAs($student)->get(route('quiz.show', [$course->slug, $quiz->id]));

        // Lewat batas waktu + toleransi
        $this->travel(6)->minutes();

        $this->actingAs($student)
            ->post(route('quiz.submit', [$course->slug, $quiz->id]), ['answers' => [$question->id => $correct->id]])
            ->assertSessionHas('error');

        $attempt = $student->quizAttempts()->where('quiz_id', $quiz->id)->first();
        $this->assertNotNull($attempt);
        $this->assertSame(0, $attempt->score);
        $this->assertTrue($attempt->expired);
        $this->assertFalse($attempt->passed);
    }

    public function test_submission_within_grace_period_is_graded_normally(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'question' => $question, 'correct' => $correct] = $this->makeQuiz(['time_limit_minutes' => 5]);
        $this->enroll($student, $course);

        $this->actingAs($student)->get(route('quiz.show', [$course->slug, $quiz->id]));

        // 10 detik lewat batas, masih dalam toleransi 30 detik
        $this->travel(5)->minutes();
        $this->travel(10)->seconds();

        $this->actingAs($student)
            ->post(route('quiz.submit', [$course->slug, $quiz->id]), ['answers' => [$question->id => $correct->id]])
            ->assertSessionHas('success');

        $attempt = $student->quizAttempts()->where('quiz_id', $quiz->id)->first();
        $this->assertSame(100, $attempt->score);
        $this->assertFalse($attempt->expired);
    }

    public function test_expired_attempt_counts_against_attempt_limit(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'question' => $question, 'correct' => $correct] = $this->makeQuiz([
            'max_attempts' => 1,
            'time_limit_minutes' => 5,
        ]);
        $this->enroll($student, $course);

        $this->actingAs($student)->get(route('quiz.show', [$course->slug, $quiz->id]));
        $this->travel(6)->minutes();
        $this->actingAs($student)->post(route('quiz.submit', [$course->slug, $quiz->id]), ['answers' => [$question->id => $correct->id]]);

        $this->assertSame(0, $quiz->remainingAttemptsFor($student));
    }

    public function test_submitting_without_opening_the_page_is_graded_normally(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'question' => $question, 'correct' => $correct] = $this->makeQuiz(['time_limit_minutes' => 5]);
        $this->enroll($student, $course);

        // Tanpa percobaan berjalan, sistem membuka percobaan baru lalu menilainya
        $this->actingAs($student)
            ->post(route('quiz.submit', [$course->slug, $quiz->id]), ['answers' => [$question->id => $correct->id]])
            ->assertSessionHas('success');

        $this->assertSame(100, $student->quizAttempts()->first()->score);
    }

    // ---------- Timer tahan muat ulang ----------

    public function test_reloading_the_page_does_not_reset_the_clock(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz] = $this->makeQuiz(['time_limit_minutes' => 10]);
        $this->enroll($student, $course);

        $this->actingAs($student)->get(route('quiz.show', [$course->slug, $quiz->id]));
        $startedAt = $student->quizAttempts()->firstOrFail()->started_at;

        $this->travel(4)->minutes();
        $this->actingAs($student)->get(route('quiz.show', [$course->slug, $quiz->id]));

        // Masih satu percobaan, dengan jam mulai yang sama persis
        $this->assertSame(1, $student->quizAttempts()->count());
        $this->assertTrue($startedAt->equalTo($student->quizAttempts()->firstOrFail()->started_at));
    }

    public function test_clock_keeps_running_across_a_fresh_session(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'question' => $question, 'correct' => $correct] = $this->makeQuiz(['time_limit_minutes' => 5]);
        $this->enroll($student, $course);

        $this->actingAs($student)->get(route('quiz.show', [$course->slug, $quiz->id]));

        // Sesi dibuang — meniru menutup browser / membuka di perangkat lain
        $this->flushSession();
        $this->travel(6)->minutes();

        $this->actingAs($student)
            ->post(route('quiz.submit', [$course->slug, $quiz->id]), ['answers' => [$question->id => $correct->id]])
            ->assertSessionHas('error');

        $attempt = $student->quizAttempts()->firstOrFail();
        $this->assertSame(0, $attempt->score);
        $this->assertTrue($attempt->expired);
    }

    public function test_opening_a_timed_quiz_consumes_an_attempt(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz] = $this->makeQuiz(['max_attempts' => 2, 'time_limit_minutes' => 5]);
        $this->enroll($student, $course);

        $this->assertSame(2, $quiz->remainingAttemptsFor($student));

        $this->actingAs($student)->get(route('quiz.show', [$course->slug, $quiz->id]));

        $this->assertSame(1, $quiz->remainingAttemptsFor($student));
    }

    public function test_opening_an_untimed_quiz_does_not_consume_an_attempt(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz] = $this->makeQuiz(['max_attempts' => 2]);
        $this->enroll($student, $course);

        $this->actingAs($student)->get(route('quiz.show', [$course->slug, $quiz->id]));

        $this->assertSame(2, $quiz->remainingAttemptsFor($student));
        $this->assertSame(0, $student->quizAttempts()->count());
    }

    public function test_abandoned_attempt_is_closed_on_next_visit(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz] = $this->makeQuiz(['time_limit_minutes' => 5]);
        $this->enroll($student, $course);

        $this->actingAs($student)->get(route('quiz.show', [$course->slug, $quiz->id]));

        // Ditinggalkan sampai lewat waktu, lalu siswa kembali
        $this->travel(10)->minutes();
        $this->actingAs($student)
            ->get(route('quiz.show', [$course->slug, $quiz->id]))
            ->assertOk();

        $abandoned = $student->quizAttempts()->orderBy('id')->first();
        $this->assertNotNull($abandoned->completed_at);
        $this->assertTrue($abandoned->expired);
        $this->assertSame(0, $abandoned->score);
    }

    public function test_new_attempt_starts_after_an_abandoned_one_when_quota_remains(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'question' => $question, 'correct' => $correct] = $this->makeQuiz([
            'max_attempts' => 2,
            'time_limit_minutes' => 5,
        ]);
        $this->enroll($student, $course);

        $this->actingAs($student)->get(route('quiz.show', [$course->slug, $quiz->id]));
        $this->travel(10)->minutes();

        // Kunjungan berikutnya menutup percobaan lama dan membuka yang baru
        $this->actingAs($student)->get(route('quiz.show', [$course->slug, $quiz->id]));

        $this->assertSame(2, $student->quizAttempts()->count());
        $this->assertSame(0, $quiz->remainingAttemptsFor($student));

        $this->actingAs($student)
            ->post(route('quiz.submit', [$course->slug, $quiz->id]), ['answers' => [$question->id => $correct->id]])
            ->assertSessionHas('success');

        $this->assertSame(100, $student->quizAttempts()->latest('id')->first()->score);
    }

    public function test_page_locks_when_quota_runs_out_after_an_abandoned_attempt(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz] = $this->makeQuiz(['max_attempts' => 1, 'time_limit_minutes' => 5]);
        $this->enroll($student, $course);

        $this->actingAs($student)->get(route('quiz.show', [$course->slug, $quiz->id]));
        $this->travel(10)->minutes();

        $this->actingAs($student)
            ->get(route('quiz.show', [$course->slug, $quiz->id]))
            ->assertOk()
            ->assertSee('Kesempatan mengerjakan sudah habis')
            ->assertDontSee('Kirim Jawaban');
    }

    public function test_in_progress_attempt_is_excluded_from_grades(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz] = $this->makeQuiz(['time_limit_minutes' => 30]);
        $this->enroll($student, $course);

        $this->actingAs($student)->get(route('quiz.show', [$course->slug, $quiz->id]));

        // Sedang dikerjakan: belum boleh muncul sebagai nilai 0 / Belum Lulus
        $this->actingAs($student)
            ->get(route('grades.index'))
            ->assertOk()
            ->assertSee('Belum Dikerjakan');
    }

    public function test_last_result_panel_ignores_the_running_attempt(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'question' => $question, 'correct' => $correct] = $this->makeQuiz([
            'max_attempts' => 3,
            'time_limit_minutes' => 30,
        ]);
        $this->enroll($student, $course);

        $this->actingAs($student)->get(route('quiz.show', [$course->slug, $quiz->id]));
        $this->actingAs($student)->post(route('quiz.submit', [$course->slug, $quiz->id]), ['answers' => [$question->id => $correct->id]]);

        // Kunjungan berikutnya membuka percobaan baru, tetapi panel nilai
        // harus tetap menampilkan hasil percobaan yang sudah selesai.
        $this->actingAs($student)
            ->get(route('quiz.show', [$course->slug, $quiz->id]))
            ->assertOk()
            ->assertSee('LULUS');
    }

    // ---------- Soal Benar/Salah ----------

    public function test_true_false_question_generates_two_options(): void
    {
        ['quiz' => $quiz] = $this->makeQuiz();

        $question = Question::create([
            'quiz_id' => $quiz->id,
            'type' => Question::TYPE_TRUE_FALSE,
            'question' => 'Laravel adalah framework PHP?',
            'order' => 2,
            'true_false_answer' => 'benar',
        ]);

        $options = $question->options()->get();
        $this->assertCount(2, $options);
        $this->assertSame(['Benar', 'Salah'], $options->pluck('option_text')->all());
        $this->assertTrue($options->firstWhere('option_text', 'Benar')->is_correct);
        $this->assertFalse($options->firstWhere('option_text', 'Salah')->is_correct);
    }

    public function test_true_false_answer_can_be_salah(): void
    {
        ['quiz' => $quiz] = $this->makeQuiz();

        $question = Question::create([
            'quiz_id' => $quiz->id,
            'type' => Question::TYPE_TRUE_FALSE,
            'question' => 'PHP adalah bahasa compiled?',
            'order' => 2,
            'true_false_answer' => 'salah',
        ]);

        $this->assertTrue($question->options()->where('option_text', 'Salah')->first()->is_correct);
        $this->assertSame('salah', $question->fresh()->true_false_answer);
    }

    public function test_editing_true_false_answer_replaces_options_without_duplicating(): void
    {
        ['quiz' => $quiz] = $this->makeQuiz();

        $question = Question::create([
            'quiz_id' => $quiz->id,
            'type' => Question::TYPE_TRUE_FALSE,
            'question' => 'Soal',
            'order' => 2,
            'true_false_answer' => 'benar',
        ]);

        $question->update(['true_false_answer' => 'salah']);

        $this->assertSame(2, $question->options()->count());
        $this->assertSame('salah', $question->fresh()->true_false_answer);
    }

    public function test_true_false_question_is_graded_by_the_normal_engine(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'question' => $mcQuestion, 'correct' => $mcCorrect] = $this->makeQuiz();
        $this->enroll($student, $course);

        $tfQuestion = Question::create([
            'quiz_id' => $quiz->id,
            'type' => Question::TYPE_TRUE_FALSE,
            'question' => 'Laravel adalah framework PHP?',
            'order' => 2,
            'true_false_answer' => 'benar',
        ]);
        $tfCorrect = $tfQuestion->options()->where('option_text', 'Benar')->first();

        $this->actingAs($student)->post(route('quiz.submit', [$course->slug, $quiz->id]), [
            'answers' => [
                $mcQuestion->id => $mcCorrect->id,
                $tfQuestion->id => $tfCorrect->id,
            ],
        ]);

        $this->assertSame(100, $student->quizAttempts()->first()->score);
    }

    public function test_multiple_choice_questions_are_unaffected(): void
    {
        ['quiz' => $quiz] = $this->makeQuiz();
        $question = $quiz->questions()->first();

        $this->assertSame(Question::TYPE_MULTIPLE_CHOICE, $question->type);
        $this->assertFalse($question->isTrueFalse());
        $this->assertNull($question->true_false_answer);
        $this->assertSame(2, $question->options()->count());
    }
}
