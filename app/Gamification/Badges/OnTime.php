<?php

namespace App\Gamification\Badges;

use App\Models\User;

class OnTime implements Badge
{
    public function key(): string
    {
        return 'on_time';
    }

    public function name(): string
    {
        return 'Tepat Waktu';
    }

    public function description(): string
    {
        return 'Mengumpulkan tugas sebelum tenggat.';
    }

    public function icon(): string
    {
        return '⏰';
    }

    public function isEarnedBy(User $user): bool
    {
        // Hanya tugas yang memang punya tenggat yang dihitung
        return $user->assignmentSubmissions()
            ->whereHas('assignment', fn ($q) => $q->whereNotNull('due_date'))
            ->whereHas('assignment', fn ($q) => $q->whereColumn('assignments.due_date', '>=', 'assignment_submissions.submitted_at'))
            ->exists();
    }
}
