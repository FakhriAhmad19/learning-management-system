<?php

namespace App\Gamification\Badges;

use App\Models\User;

class FirstStep implements Badge
{
    public function key(): string
    {
        return 'first_step';
    }

    public function name(): string
    {
        return 'Langkah Pertama';
    }

    public function description(): string
    {
        return 'Menyelesaikan materi pertama kamu.';
    }

    public function icon(): string
    {
        return '👣';
    }

    public function isEarnedBy(User $user): bool
    {
        return $user->completedLessons()->exists();
    }
}
