<?php

namespace App\Gamification\Badges;

use App\Models\User;

class PerfectScore implements Badge
{
    public function key(): string
    {
        return 'perfect_score';
    }

    public function name(): string
    {
        return 'Nilai Sempurna';
    }

    public function description(): string
    {
        return 'Mendapat nilai 100 pada sebuah kuis.';
    }

    public function icon(): string
    {
        return '💯';
    }

    public function isEarnedBy(User $user): bool
    {
        return $user->quizAttempts()
            ->where('score', 100)
            ->where('passed', true)
            ->exists();
    }
}
