<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\PointAward;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\User;
use App\Models\UserBadge;
use App\Notifications\BadgeEarned;
use App\Services\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GamificationTest extends TestCase
{
    use RefreshDatabase;

    private function student(string $name = 'Siswa'): User
    {
        Role::firstOrCreate(['name' => 'Student']);
        $user = User::factory()->create(['name' => $name]);
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
     * Kursus dengan sejumlah materi, satu kuis, dan satu tugas.
     *
     * @return array{course: Course, lessons: Collection, quiz: Quiz, assignment: Assignment, correct: QuestionOption, question: Question}
     */
    private function makeCourse(int $lessonCount = 1, ?User $instructor = null, string $title = 'Kursus Poin'): array
    {
        $course = Course::create([
            'instructor_id' => ($instructor ?? $this->instructor())->id,
            'title' => $title,
            'about' => 'deskripsi',
            'price' => 0,
            'status' => 'published',
        ]);
        $module = Module::create(['course_id' => $course->id, 'title' => 'Modul 1', 'order' => 1]);

        $lessons = collect(range(1, $lessonCount))->map(fn (int $i) => Lesson::create([
            'module_id' => $module->id,
            'title' => 'Materi '.$i,
            'content' => 'isi',
            'order' => $i,
        ]));

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

        return compact('course', 'lessons', 'quiz', 'assignment', 'correct', 'question');
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

    private function gamification(): GamificationService
    {
        return app(GamificationService::class);
    }

    // ---------- Poin ----------

    public function test_completing_a_lesson_awards_points(): void
    {
        $student = $this->student();
        ['course' => $course, 'lessons' => $lessons] = $this->makeCourse(3);
        $this->enroll($student, $course);

        $this->actingAs($student)->post(route('learn.complete', [$course->slug, $lessons->first()->slug]));

        $this->assertSame(10, $this->gamification()->totalPoints($student));
    }

    public function test_points_are_not_awarded_twice_for_the_same_lesson(): void
    {
        $student = $this->student();
        ['course' => $course, 'lessons' => $lessons] = $this->makeCourse(3);
        $enrollment = $this->enroll($student, $course);
        $lesson = $lessons->first();

        // Klik "Tandai Selesai" berkali-kali + sinkronisasi ulang
        $this->actingAs($student)->post(route('learn.complete', [$course->slug, $lesson->slug]));
        $this->actingAs($student)->post(route('learn.complete', [$course->slug, $lesson->slug]));
        $enrollment->fresh()->recalculateProgress();
        $this->gamification()->sync($enrollment->fresh());

        $this->assertSame(10, $this->gamification()->totalPoints($student));
        $this->assertSame(1, PointAward::where('user_id', $student->id)->count());
    }

    public function test_passing_a_quiz_awards_points_once_even_after_retries(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'question' => $question, 'correct' => $correct] = $this->makeCourse();
        $this->enroll($student, $course);

        $this->actingAs($student)->post(route('quiz.submit', [$course->slug, $quiz->id]), ['answers' => [$question->id => $correct->id]]);
        $this->actingAs($student)->post(route('quiz.submit', [$course->slug, $quiz->id]), ['answers' => [$question->id => $correct->id]]);

        $this->assertSame(1, PointAward::where('user_id', $student->id)->where('type', PointAward::TYPE_QUIZ)->count());
        $this->assertSame(25, (int) PointAward::where('user_id', $student->id)->where('type', PointAward::TYPE_QUIZ)->sum('points'));
    }

    public function test_assignment_points_require_a_passing_grade(): void
    {
        $student = $this->student();
        ['course' => $course, 'assignment' => $assignment] = $this->makeCourse();
        $this->enroll($student, $course);

        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'content' => 'jawaban',
            'submitted_at' => now(),
        ]);

        // Baru dikumpulkan: belum ada poin
        $this->assertSame(0, PointAward::where('user_id', $student->id)->where('type', PointAward::TYPE_ASSIGNMENT)->count());

        // Dinilai di bawah batas lulus: tetap tanpa poin
        $submission->update(['score' => 40, 'graded_at' => now()]);
        $this->assertSame(0, PointAward::where('user_id', $student->id)->where('type', PointAward::TYPE_ASSIGNMENT)->count());

        // Dinilai lulus: poin diberikan
        $submission->update(['score' => 80]);
        $this->assertSame(30, (int) PointAward::where('user_id', $student->id)->where('type', PointAward::TYPE_ASSIGNMENT)->sum('points'));
    }

    public function test_completing_a_course_awards_bonus_points(): void
    {
        $student = $this->student();
        // Satu materi saja supaya kursus langsung tuntas
        ['course' => $course, 'lessons' => $lessons, 'quiz' => $quiz, 'assignment' => $assignment, 'question' => $question, 'correct' => $correct] = $this->makeCourse();
        $assignment->delete();
        $this->enroll($student, $course);

        $this->actingAs($student)->post(route('learn.complete', [$course->slug, $lessons->first()->slug]));
        $this->actingAs($student)->post(route('quiz.submit', [$course->slug, $quiz->id]), ['answers' => [$question->id => $correct->id]]);

        // 10 (materi) + 25 (kuis) + 100 (kursus tuntas)
        $this->assertSame(135, $this->gamification()->totalPoints($student));
    }

    public function test_no_course_bonus_while_course_is_unfinished(): void
    {
        $student = $this->student();
        ['course' => $course, 'lessons' => $lessons] = $this->makeCourse(3);
        $this->enroll($student, $course);

        $this->actingAs($student)->post(route('learn.complete', [$course->slug, $lessons->first()->slug]));

        $this->assertSame(0, PointAward::where('user_id', $student->id)->where('type', PointAward::TYPE_COURSE)->count());
    }

    public function test_points_are_attributed_to_the_right_course(): void
    {
        $student = $this->student();
        ['course' => $courseA, 'lessons' => $lessonsA] = $this->makeCourse(2, title: 'Kursus A');
        ['course' => $courseB, 'lessons' => $lessonsB] = $this->makeCourse(2, title: 'Kursus B');
        $this->enroll($student, $courseA);
        $this->enroll($student, $courseB);

        $this->actingAs($student)->post(route('learn.complete', [$courseA->slug, $lessonsA->first()->slug]));

        $this->assertSame(10, $this->gamification()->pointsInCourse($student, $courseA));
        $this->assertSame(0, $this->gamification()->pointsInCourse($student, $courseB));
    }

    // ---------- Badge ----------

    public function test_first_step_badge_is_awarded_on_first_lesson(): void
    {
        $student = $this->student();
        ['course' => $course, 'lessons' => $lessons] = $this->makeCourse(3);
        $this->enroll($student, $course);

        $this->actingAs($student)->post(route('learn.complete', [$course->slug, $lessons->first()->slug]));

        $this->assertDatabaseHas('user_badges', ['user_id' => $student->id, 'badge' => 'first_step']);
    }

    public function test_diligent_learner_needs_ten_lessons(): void
    {
        $student = $this->student();
        ['course' => $course, 'lessons' => $lessons] = $this->makeCourse(10);
        $enrollment = $this->enroll($student, $course);

        foreach ($lessons->take(9) as $lesson) {
            $this->actingAs($student)->post(route('learn.complete', [$course->slug, $lesson->slug]));
        }
        $this->assertDatabaseMissing('user_badges', ['user_id' => $student->id, 'badge' => 'diligent_learner']);

        $this->actingAs($student)->post(route('learn.complete', [$course->slug, $lessons->last()->slug]));
        $this->assertDatabaseHas('user_badges', ['user_id' => $student->id, 'badge' => 'diligent_learner']);
    }

    public function test_perfect_score_badge_requires_a_hundred(): void
    {
        $student = $this->student();
        ['course' => $course, 'quiz' => $quiz, 'question' => $question, 'correct' => $correct] = $this->makeCourse();
        $this->enroll($student, $course);

        $this->actingAs($student)->post(route('quiz.submit', [$course->slug, $quiz->id]), ['answers' => [$question->id => $correct->id]]);

        $this->assertDatabaseHas('user_badges', ['user_id' => $student->id, 'badge' => 'perfect_score']);
    }

    public function test_graduate_badge_on_course_completion(): void
    {
        $student = $this->student();
        ['course' => $course, 'lessons' => $lessons, 'quiz' => $quiz, 'assignment' => $assignment, 'question' => $question, 'correct' => $correct] = $this->makeCourse();
        $assignment->delete();
        $this->enroll($student, $course);

        $this->actingAs($student)->post(route('learn.complete', [$course->slug, $lessons->first()->slug]));
        $this->actingAs($student)->post(route('quiz.submit', [$course->slug, $quiz->id]), ['answers' => [$question->id => $correct->id]]);

        $this->assertDatabaseHas('user_badges', ['user_id' => $student->id, 'badge' => 'graduate']);
    }

    public function test_on_time_badge_only_counts_submissions_before_the_due_date(): void
    {
        $student = $this->student();
        ['course' => $course, 'assignment' => $assignment] = $this->makeCourse();
        $enrollment = $this->enroll($student, $course);

        $assignment->update(['due_date' => now()->subDay()]);
        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'content' => 'terlambat',
            'submitted_at' => now(),
        ]);
        $this->gamification()->sync($enrollment->fresh());
        $this->assertDatabaseMissing('user_badges', ['user_id' => $student->id, 'badge' => 'on_time']);

        $assignment->update(['due_date' => now()->addWeek()]);
        $this->gamification()->sync($enrollment->fresh());
        $this->assertDatabaseHas('user_badges', ['user_id' => $student->id, 'badge' => 'on_time']);
    }

    public function test_badge_is_not_awarded_twice(): void
    {
        $student = $this->student();
        ['course' => $course, 'lessons' => $lessons] = $this->makeCourse(3);
        $enrollment = $this->enroll($student, $course);

        $this->actingAs($student)->post(route('learn.complete', [$course->slug, $lessons->first()->slug]));
        $this->gamification()->syncBadges($student);
        $this->gamification()->syncBadges($student);

        $this->assertSame(1, UserBadge::where('user_id', $student->id)->where('badge', 'first_step')->count());
    }

    public function test_badge_notification_is_sent_once(): void
    {
        Notification::fake();

        $student = $this->student();
        ['course' => $course, 'lessons' => $lessons] = $this->makeCourse(3);
        $this->enroll($student, $course);

        $this->actingAs($student)->post(route('learn.complete', [$course->slug, $lessons->first()->slug]));
        $this->gamification()->syncBadges($student);

        Notification::assertSentToTimes($student, BadgeEarned::class, 1);
    }

    // ---------- Leaderboard ----------

    public function test_leaderboard_ranks_by_points_within_the_course(): void
    {
        ['course' => $course, 'lessons' => $lessons] = $this->makeCourse(3);
        $top = $this->student('Juara');
        $mid = $this->student('Tengah');
        $this->enroll($top, $course);
        $this->enroll($mid, $course);

        foreach ($lessons as $lesson) {
            $this->actingAs($top)->post(route('learn.complete', [$course->slug, $lesson->slug]));
        }
        $this->actingAs($mid)->post(route('learn.complete', [$course->slug, $lessons->first()->slug]));

        $board = $this->gamification()->leaderboard($course);

        $this->assertSame('Juara', $board[0]['user']->name);
        $this->assertSame(30, $board[0]['points']);
        $this->assertSame(1, $board[0]['rank']);
        $this->assertSame('Tengah', $board[1]['user']->name);
        $this->assertSame(2, $board[1]['rank']);
    }

    public function test_leaderboard_only_counts_points_from_that_course(): void
    {
        ['course' => $courseA, 'lessons' => $lessonsA] = $this->makeCourse(2, title: 'Kursus A');
        ['course' => $courseB, 'lessons' => $lessonsB] = $this->makeCourse(2, title: 'Kursus B');

        $student = $this->student('Ganda');
        $this->enroll($student, $courseA);
        $this->enroll($student, $courseB);

        foreach ($lessonsA as $lesson) {
            $this->actingAs($student)->post(route('learn.complete', [$courseA->slug, $lesson->slug]));
        }
        $this->actingAs($student)->post(route('learn.complete', [$courseB->slug, $lessonsB->first()->slug]));

        $this->assertSame(20, $this->gamification()->leaderboard($courseA)[0]['points']);
        $this->assertSame(10, $this->gamification()->leaderboard($courseB)[0]['points']);
    }

    public function test_leaderboard_excludes_non_participants(): void
    {
        ['course' => $course, 'lessons' => $lessons] = $this->makeCourse(2);
        $member = $this->student('Peserta');
        $outsider = $this->student('Bukan Peserta');
        $this->enroll($member, $course);

        $this->actingAs($member)->post(route('learn.complete', [$course->slug, $lessons->first()->slug]));

        $names = $this->gamification()->leaderboard($course)->map(fn ($e) => $e['user']->name);

        $this->assertContains('Peserta', $names);
        $this->assertNotContains('Bukan Peserta', $names);
    }

    public function test_rank_is_null_without_points(): void
    {
        ['course' => $course] = $this->makeCourse();
        $student = $this->student();
        $this->enroll($student, $course);

        $this->assertNull($this->gamification()->rankInCourse($student, $course));
    }

    // ---------- Halaman ----------

    public function test_achievements_page_shows_points_and_badges(): void
    {
        $student = $this->student();
        ['course' => $course, 'lessons' => $lessons] = $this->makeCourse(3);
        $this->enroll($student, $course);

        $this->actingAs($student)->post(route('learn.complete', [$course->slug, $lessons->first()->slug]));

        $this->actingAs($student)
            ->get(route('achievements.index'))
            ->assertOk()
            ->assertSee('Langkah Pertama')
            ->assertSee('Kolektor')          // badge terkunci tetap tampil
            ->assertSee('Belum diraih')
            ->assertSee('Kursus Poin');
    }

    public function test_achievements_page_requires_login(): void
    {
        $this->get(route('achievements.index'))->assertRedirect(route('login'));
    }

    public function test_leaderboard_page_is_gated_to_participants(): void
    {
        ['course' => $course] = $this->makeCourse();
        $outsider = $this->student();

        $this->actingAs($outsider)
            ->get(route('leaderboard.show', $course->slug))
            ->assertRedirect(route('courses.show', $course->slug));
    }

    public function test_instructor_can_view_own_course_leaderboard(): void
    {
        $instructor = $this->instructor();
        ['course' => $course] = $this->makeCourse(1, $instructor);

        $this->actingAs($instructor)
            ->get(route('leaderboard.show', $course->slug))
            ->assertOk();
    }

    public function test_leaderboard_page_marks_the_current_user(): void
    {
        ['course' => $course, 'lessons' => $lessons] = $this->makeCourse(2);
        $student = $this->student('Saya Sendiri');
        $this->enroll($student, $course);

        $this->actingAs($student)->post(route('learn.complete', [$course->slug, $lessons->first()->slug]));

        $this->actingAs($student)
            ->get(route('leaderboard.show', $course->slug))
            ->assertOk()
            ->assertSee('Saya Sendiri')
            ->assertSee('Kamu');
    }
}
