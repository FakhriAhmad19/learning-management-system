<?php

namespace App\Policies;

use App\Models\QuizAttempt;
use App\Models\User;

class QuizAttemptPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('Admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('Instructor');
    }

    public function view(User $user, QuizAttempt $attempt): bool
    {
        return $this->owns($user, $attempt);
    }

    /**
     * Pengerjaan dibuat oleh siswa lewat halaman kuis, bukan dari panel admin.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, QuizAttempt $attempt): bool
    {
        return $this->owns($user, $attempt);
    }

    public function delete(User $user, QuizAttempt $attempt): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Instructor hanya boleh menilai pengerjaan pada kursus miliknya.
     */
    private function owns(User $user, QuizAttempt $attempt): bool
    {
        return $user->id === $attempt->quiz?->module?->course?->instructor_id;
    }
}
