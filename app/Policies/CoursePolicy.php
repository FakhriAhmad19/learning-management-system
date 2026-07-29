<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    /**
     * Admin memiliki akses penuh ke semua aksi.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('Admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('Instructor');
    }

    public function view(User $user, Course $course): bool
    {
        return $this->owns($user, $course);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Instructor');
    }

    public function update(User $user, Course $course): bool
    {
        return $this->owns($user, $course);
    }

    public function delete(User $user, Course $course): bool
    {
        return $this->owns($user, $course);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('Instructor');
    }

    /**
     * Instructor hanya boleh mengelola kursus miliknya sendiri (PRD §3).
     */
    private function owns(User $user, Course $course): bool
    {
        return $user->id === $course->instructor_id;
    }
}
