<?php

namespace App\Policies;

use App\Models\LearningPath;
use App\Models\User;

class LearningPathPolicy
{
    /**
     * Jalur belajar dapat merangkai kursus dari beberapa pengajar sekaligus,
     * sehingga penyusunannya hanya untuk Admin — sama seperti Kategori.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('Admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, LearningPath $path): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, LearningPath $path): bool
    {
        return false;
    }

    public function delete(User $user, LearningPath $path): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
