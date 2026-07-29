<?php

namespace App\Gamification\Badges;

use App\Models\User;

class Collector implements Badge
{
    private const REQUIRED_COURSES = 3;

    public function key(): string
    {
        return 'collector';
    }

    public function name(): string
    {
        return 'Kolektor';
    }

    public function description(): string
    {
        return 'Menyelesaikan '.self::REQUIRED_COURSES.' kursus.';
    }

    public function icon(): string
    {
        return '🏆';
    }

    public function isEarnedBy(User $user): bool
    {
        return $user->enrollments()->where('status', 'completed')->count() >= self::REQUIRED_COURSES;
    }
}
