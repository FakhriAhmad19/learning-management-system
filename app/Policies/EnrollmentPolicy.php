<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('Admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('Instructor');
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        return $this->owns($user, $enrollment);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Instructor');
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        return $this->owns($user, $enrollment);
    }

    public function delete(User $user, Enrollment $enrollment): bool
    {
        return $this->owns($user, $enrollment);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('Instructor');
    }

    /**
     * Instructor hanya boleh mengelola pendaftaran pada kursus miliknya.
     */
    private function owns(User $user, Enrollment $enrollment): bool
    {
        return $user->id === $enrollment->course?->instructor_id;
    }
}
