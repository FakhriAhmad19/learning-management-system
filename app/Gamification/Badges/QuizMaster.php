<?php

namespace App\Gamification\Badges;

use App\Models\User;

class QuizMaster implements Badge
{
    private const REQUIRED_QUIZZES = 5;

    public function key(): string
    {
        return 'quiz_master';
    }

    public function name(): string
    {
        return 'Ahli Kuis';
    }

    public function description(): string
    {
        return 'Lulus '.self::REQUIRED_QUIZZES.' kuis berbeda.';
    }

    public function icon(): string
    {
        return '🧠';
    }

    public function isEarnedBy(User $user): bool
    {
        // distinct: mengulang kuis yang sama tidak menambah hitungan
        return $user->quizAttempts()
            ->where('passed', true)
            ->distinct()
            ->count('quiz_id') >= self::REQUIRED_QUIZZES;
    }
}
