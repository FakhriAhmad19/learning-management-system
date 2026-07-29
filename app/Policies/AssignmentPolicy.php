<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\User;

class AssignmentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('Admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('Instructor');
    }

    public function view(User $user, Assignment $assignment): bool
    {
        return $this->owns($user, $assignment);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Instructor');
    }

    public function update(User $user, Assignment $assignment): bool
    {
        return $this->owns($user, $assignment);
    }

    public function delete(User $user, Assignment $assignment): bool
    {
        return $this->owns($user, $assignment);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('Instructor');
    }

    /**
     * Instructor hanya boleh mengelola tugas pada modul/kursus miliknya.
     */
    private function owns(User $user, Assignment $assignment): bool
    {
        return $user->id === $assignment->module?->course?->instructor_id;
    }
}
