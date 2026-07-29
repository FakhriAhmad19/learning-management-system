<?php

namespace App\Gamification\Badges;

use App\Models\User;

class Graduate implements Badge
{
    public function key(): string
    {
        return 'graduate';
    }

    public function name(): string
    {
        return 'Lulusan';
    }

    public function description(): string
    {
        return 'Menyelesaikan satu kursus hingga tuntas.';
    }

    public function icon(): string
    {
        return '🎓';
    }

    public function isEarnedBy(User $user): bool
    {
        return $user->enrollments()->where('status', 'completed')->exists();
    }
}
