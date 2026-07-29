<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Hanya Admin yang boleh mengelola pengguna & role.
     * Admin di-grant penuh via before(); peran lain ditolak (metode mengembalikan false).
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('Admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, User $model): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $model): bool
    {
        return false;
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
