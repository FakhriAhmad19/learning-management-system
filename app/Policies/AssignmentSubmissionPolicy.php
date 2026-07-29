<?php

namespace App\Policies;

use App\Models\AssignmentSubmission;
use App\Models\User;

class AssignmentSubmissionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('Admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('Instructor');
    }

    public function view(User $user, AssignmentSubmission $submission): bool
    {
        return $this->owns($user, $submission);
    }

    /**
     * Pengumpulan dibuat oleh siswa lewat halaman belajar, bukan dari panel admin.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AssignmentSubmission $submission): bool
    {
        return $this->owns($user, $submission);
    }

    public function delete(User $user, AssignmentSubmission $submission): bool
    {
        return $this->owns($user, $submission);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Instructor hanya boleh menilai pengumpulan pada kursus miliknya.
     */
    private function owns(User $user, AssignmentSubmission $submission): bool
    {
        return $user->id === $submission->assignment?->module?->course?->instructor_id;
    }
}
