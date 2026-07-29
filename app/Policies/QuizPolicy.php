<?php

namespace App\Policies;

use App\Models\Quiz;
use App\Models\User;

class QuizPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('Admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('Instructor');
    }

    public function view(User $user, Quiz $quiz): bool
    {
        return $this->owns($user, $quiz);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Instructor');
    }

    public function update(User $user, Quiz $quiz): bool
    {
        return $this->owns($user, $quiz);
    }

    public function delete(User $user, Quiz $quiz): bool
    {
        return $this->owns($user, $quiz);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('Instructor');
    }

    /**
     * Instructor hanya boleh mengelola kuis pada modul/kursus miliknya.
     */
    private function owns(User $user, Quiz $quiz): bool
    {
        return $user->id === $quiz->module?->course?->instructor_id;
    }
}
