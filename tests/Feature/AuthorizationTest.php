<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_only_admin_can_access_user_resource(): void
    {
        $this->actingAs($this->instructor())->get('/admin/users')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/users')->assertOk();
    }

    public function test_only_admin_can_manage_categories(): void
    {
        $this->actingAs($this->instructor())->get('/admin/categories')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/categories')->assertOk();
    }

    public function test_instructor_can_access_course_and_quiz_resources(): void
    {
        $instructor = $this->instructor();
        $this->actingAs($instructor)->get('/admin/courses')->assertOk();
        $this->actingAs($instructor)->get('/admin/quizzes')->assertOk();
    }

    public function test_instructor_can_access_assignment_resources(): void
    {
        $instructor = $this->instructor();
        $this->actingAs($instructor)->get('/admin/assignments')->assertOk();
        $this->actingAs($instructor)->get('/admin/assignment-submissions')->assertOk();
    }

    public function test_instructor_cannot_grade_another_instructors_submission(): void
    {
        $owner = $this->instructor();
        $other = $this->instructor();

        $course = Course::create([
            'instructor_id' => $owner->id,
            'title' => 'Kursus Milik Owner',
            'about' => 'x',
            'price' => 0,
            'status' => 'published',
        ]);
        $module = Module::create(['course_id' => $course->id, 'title' => 'Modul 1', 'order' => 1]);
        $assignment = Assignment::create([
            'module_id' => $module->id,
            'title' => 'Tugas 1',
            'max_score' => 100,
            'passing_score' => 60,
        ]);
        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'user_id' => User::factory()->create()->id,
            'content' => 'jawaban',
            'submitted_at' => now(),
        ]);

        $this->assertTrue($owner->can('update', $submission));
        $this->assertFalse($other->can('update', $submission));
        $this->assertTrue($this->admin()->can('update', $submission));
    }

    public function test_instructor_cannot_edit_another_instructors_course(): void
    {
        $owner = $this->instructor();
        $other = $this->instructor();
        $course = Course::create([
            'instructor_id' => $owner->id,
            'title' => 'Kursus Milik Owner',
            'about' => 'x',
            'price' => 0,
            'status' => 'draft',
        ]);

        $this->assertTrue($owner->can('update', $course));
        $this->assertFalse($other->can('update', $course));
        $this->assertTrue($this->admin()->can('update', $course));
    }
}
