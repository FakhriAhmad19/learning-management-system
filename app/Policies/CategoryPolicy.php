<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Kategori adalah taksonomi global, jadi hanya Admin yang boleh mengelolanya.
     * Instructor tetap bisa memilih kategori lewat form kursus (Select), tanpa
     * bisa menambah/mengubah daftar kategorinya.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('Admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Category $category): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Category $category): bool
    {
        return false;
    }

    public function delete(User $user, Category $category): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
