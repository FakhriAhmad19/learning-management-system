<?php

namespace App\Policies;

use App\Models\Discussion;
use App\Models\User;

class DiscussionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('Admin') ? true : null;
    }

    /**
     * Yang boleh menghapus: penulisnya sendiri, atau pengajar kelas
     * (moderasi diskusi di kursusnya).
     */
    public function delete(User $user, Discussion $discussion): bool
    {
        if ($user->id === $discussion->user_id) {
            return true;
        }

        return $user->id === $discussion->lesson?->module?->course?->instructor_id;
    }
}
