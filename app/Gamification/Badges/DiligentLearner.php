<?php

namespace App\Gamification\Badges;

use App\Models\User;

class DiligentLearner implements Badge
{
    private const REQUIRED_LESSONS = 10;

    public function key(): string
    {
        return 'diligent_learner';
    }

    public function name(): string
    {
        return 'Rajin Belajar';
    }

    public function description(): string
    {
        return 'Menyelesaikan '.self::REQUIRED_LESSONS.' materi.';
    }

    public function icon(): string
    {
        return '📚';
    }

    public function isEarnedBy(User $user): bool
    {
        return $user->completedLessons()->count() >= self::REQUIRED_LESSONS;
    }
}
