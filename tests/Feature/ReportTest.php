<?php

namespace Tests\Feature;

use App\Filament\Pages\Gradebook;
use App\Filament\Pages\Reports;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function student(string $name = 'Siswa Uji'): User
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

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'Admin']);
        $user = User::factory()->create();
        $user->assignRole('Admin');

        return $user;
    }

    /**
     * @return array{course: Course, quiz: Quiz, assignment: Assignment, lesson: Lesson, correct: QuestionOption, question: Question}
     */
    private function makeCourse(?User $instructor = null, string $title = 'Kursus Laporan'): array
    {
        $course = Course::create([
            'instructor_id' => ($instructor ?? $this->instructor())->id,
            'category_id' => Category::firstOrCreate(['slug' => 'backend'], ['name' => 'Backend'])->id,
            'title' => $title,
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

        return compact('course', 'quiz', 'assignment', 'lesson', 'correct', 'question');
    }

    private function enroll(User $student, Course $course, string $status = 'active', int $progress = 0): Enrollment
    {
        return Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'amount_paid' => 0,
            'status' => $status,
            'progress_percentage' => $progress,
        ]);
    }

    private function capture(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return ob_get_clean();
    }

    private function reports(): ReportService
    {
        return app(ReportService::class);
    }

    // ---------- Ringkasan kursus ----------

    public function test_course_summary_counts_students_and_completion(): void
    {
        ['course' => $course] = $this->makeCourse();

        $this->enroll($this->student('A'), $course, 'active', 40);
        $this->enroll($this->student('B'), $course, 'completed', 100);
        $this->enroll($this->student('C'), $course, 'completed', 100);

        $summary = $this->reports()->courseSummaries(null)->firstWhere('course.id', $course->id);

        $this->assertSame(3, $summary['students']);
        $this->assertSame(1, $summary['active']);
        $this->assertSame(2, $summary['completed']);
        $this->assertSame(80, $summary['average_progress']); // (40+100+100)/3
        $this->assertSame(67, $summary['completion_rate']);  // 2 dari 3
    }

    public function test_course_summary_handles_course_without_students(): void
    {
        ['course' => $course] = $this->makeCourse();

        $summary = $this->reports()->courseSummaries(null)->firstWhere('course.id', $course->id);

        $this->assertSame(0, $summary['students']);
        $this->assertSame(0, $summary['average_progress']);
        $this->assertSame(0, $summary['completion_rate']);
    }

    public function test_summary_is_scoped_to_the_instructor(): void
    {
        $owner = $this->instructor();
        $other = $this->instructor();
        $this->makeCourse($owner, 'Kursus Owner');
        $this->makeCourse($other, 'Kursus Lain');

        $titles = $this->reports()->courseSummaries($owner->id)->pluck('course.title');

        $this->assertContains('Kursus Owner', $titles);
        $this->assertNotContains('Kursus Lain', $titles);
        $this->assertCount(2, $this->reports()->courseSummaries(null));
    }

    public function test_pending_grading_counts_assignments_and_essay_attempts(): void
    {
        ['course' => $course, 'quiz' => $quiz, 'assignment' => $assignment] = $this->makeCourse();
        $student = $this->student();
        $this->enroll($student, $course);

        // Tugas dikumpulkan, belum dinilai
        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'content' => 'jawaban',
            'submitted_at' => now(),
        ]);

        // Kuis berisi esai, dikirim, belum dinilai
        Question::create([
            'quiz_id' => $quiz->id,
            'type' => Question::TYPE_ESSAY,
            'question' => 'Jelaskan.',
            'order' => 2,
        ]);
        QuizAttempt::create([
            'user_id' => $student->id,
            'quiz_id' => $quiz->id,
            'answers' => [],
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $this->assertSame(2, $this->reports()->pendingGradingCount($course));
    }

    public function test_pending_grading_ignores_graded_and_expired_work(): void
    {
        ['course' => $course, 'quiz' => $quiz, 'assignment' => $assignment] = $this->makeCourse();
        $student = $this->student();
        $this->enroll($student, $course);

        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'content' => 'jawaban',
            'submitted_at' => now(),
            'score' => 80,
            'graded_at' => now(),
        ]);

        QuizAttempt::create([
            'user_id' => $student->id,
            'quiz_id' => $quiz->id,
            'answers' => [],
            'started_at' => now(),
            'completed_at' => now(),
            'expired' => true,
        ]);

        $this->assertSame(0, $this->reports()->pendingGradingCount($course));
    }

    // ---------- Peserta ----------

    public function test_participants_are_sorted_by_name_and_exclude_pending(): void
    {
        ['course' => $course] = $this->makeCourse();

        $this->enroll($this->student('Zulkifli'), $course);
        $this->enroll($this->student('Ahmad'), $course);
        $this->enroll($this->student('Pending'), $course, 'pending');

        $names = $this->reports()->participants($course)->map(fn ($e) => $e->student->name);

        $this->assertSame(['Ahmad', 'Zulkifli'], $names->all());
    }

    // ---------- Ekspor CSV ----------

    public function test_gradebook_export_contains_scores_and_pending_marker(): void
    {
        $instructor = $this->instructor();
        ['course' => $course, 'quiz' => $quiz, 'assignment' => $assignment] = $this->makeCourse($instructor);
        $student = $this->student('Budi Siswa');
        $this->enroll($student, $course, 'active', 50);

        QuizAttempt::create([
            'user_id' => $student->id, 'quiz_id' => $quiz->id,
            'score' => 90, 'passed' => true, 'answers' => [],
            'started_at' => now(), 'completed_at' => now(), 'graded_at' => now(),
        ]);
        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'content' => 'jawaban',
            'submitted_at' => now(),
        ]);

        $csv = $this->capture(
            Livewire::actingAs($instructor)->test(Gradebook::class)->instance()->exportGradebook()
        );

        $this->assertStringContainsString('Nama,Email', $csv);
        $this->assertStringContainsString('Budi Siswa', $csv);
        $this->assertStringContainsString('Kuis 1 (Kuis, maks 100)', $csv);
        $this->assertStringContainsString('Tugas 1 (Tugas, maks 100)', $csv);
        $this->assertStringContainsString('90', $csv);
        $this->assertStringContainsString('Perlu dinilai', $csv);
    }

    public function test_gradebook_export_starts_with_utf8_bom(): void
    {
        $instructor = $this->instructor();
        ['course' => $course] = $this->makeCourse($instructor);
        $this->enroll($this->student(), $course);

        $csv = $this->capture(
            Livewire::actingAs($instructor)->test(Gradebook::class)->instance()->exportGradebook()
        );

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
    }

    public function test_participants_export_lists_enrolled_students(): void
    {
        $instructor = $this->instructor();
        ['course' => $course] = $this->makeCourse($instructor);
        $this->enroll($this->student('Citra'), $course, 'completed', 100);

        $csv = $this->capture(
            Livewire::actingAs($instructor)->test(Gradebook::class)->instance()->exportParticipants()
        );

        $this->assertStringContainsString('Nama,Email,Status', $csv);
        $this->assertStringContainsString('Citra', $csv);
        $this->assertStringContainsString('Selesai', $csv);
    }

    public function test_summary_export_contains_one_row_per_course(): void
    {
        $instructor = $this->instructor();
        ['course' => $course] = $this->makeCourse($instructor, 'Kursus Ekspor');
        $this->enroll($this->student(), $course, 'completed', 100);

        $csv = $this->capture(
            Livewire::actingAs($instructor)->test(Reports::class)->instance()->exportSummary()
        );

        $this->assertStringContainsString('Kursus,Kategori,Pengajar', $csv);
        $this->assertStringContainsString('Kursus Ekspor', $csv);
        $this->assertStringContainsString('Backend', $csv);
    }

    public function test_export_is_scoped_to_the_instructors_own_courses(): void
    {
        $owner = $this->instructor();
        $other = $this->instructor();
        $this->makeCourse($owner, 'Kursus Owner');
        $this->makeCourse($other, 'Kursus Rahasia');

        $csv = $this->capture(
            Livewire::actingAs($owner)->test(Reports::class)->instance()->exportSummary()
        );

        $this->assertStringContainsString('Kursus Owner', $csv);
        $this->assertStringNotContainsString('Kursus Rahasia', $csv);
    }

    public function test_export_returns_nothing_without_a_selected_course(): void
    {
        $instructor = $this->instructor();

        // Instruktur tanpa kursus sama sekali
        $page = Livewire::actingAs($instructor)->test(Gradebook::class)->instance();

        $this->assertNull($page->exportGradebook());
        $this->assertNull($page->exportParticipants());
    }

    // ---------- Halaman Laporan ----------

    public function test_instructor_can_open_reports_page(): void
    {
        $this->actingAs($this->instructor())->get('/admin/reports')->assertOk();
    }

    public function test_student_cannot_open_reports_page(): void
    {
        $this->actingAs($this->student())->get('/admin/reports')->assertForbidden();
    }

    public function test_reports_page_shows_only_own_courses(): void
    {
        $owner = $this->instructor();
        $other = $this->instructor();
        $this->makeCourse($owner, 'Kursus Owner');
        $this->makeCourse($other, 'Kursus Rahasia');

        Livewire::actingAs($owner)
            ->test(Reports::class)
            ->assertSee('Kursus Owner')
            ->assertDontSee('Kursus Rahasia');
    }

    public function test_admin_sees_all_courses_in_reports(): void
    {
        $this->makeCourse($this->instructor(), 'Kursus Satu');
        $this->makeCourse($this->instructor(), 'Kursus Dua');

        Livewire::actingAs($this->admin())
            ->test(Reports::class)
            ->assertSee('Kursus Satu')
            ->assertSee('Kursus Dua');
    }

    public function test_reports_totals_aggregate_across_courses(): void
    {
        $instructor = $this->instructor();
        ['course' => $a] = $this->makeCourse($instructor, 'Kursus A');
        ['course' => $b] = $this->makeCourse($instructor, 'Kursus B');

        $this->enroll($this->student('S1'), $a, 'completed', 100);
        $this->enroll($this->student('S2'), $a, 'active', 20);
        $this->enroll($this->student('S3'), $b, 'completed', 100);

        $totals = Livewire::actingAs($instructor)->test(Reports::class)->instance()->totals;

        $this->assertSame(2, $totals['courses']);
        $this->assertSame(3, $totals['students']);
        $this->assertSame(2, $totals['completed']);
        $this->assertSame(67, $totals['completion_rate']);
    }
}
