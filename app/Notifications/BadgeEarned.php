<?php

namespace App\Notifications;

use App\Gamification\Badges\Badge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Badge diumumkan lewat lonceng in-app saja — tidak lewat email, supaya
 * pencapaian kecil tidak membanjiri kotak masuk siswa.
 */
class BadgeEarned extends Notification implements ShouldQueue
{
    use Queueable;

    public string $badgeKey;

    private string $badgeName;

    private string $badgeIcon;

    public function __construct(Badge $badge)
    {
        // Simpan nilai primitif agar badge tetap bisa diserialisasi ke antrean
        $this->badgeKey = $badge->key();
        $this->badgeName = $badge->name();
        $this->badgeIcon = $badge->icon();
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'badge_earned',
            'title' => 'Badge baru: '.$this->badgeName,
            'message' => $this->badgeIcon.' Kamu meraih badge '.$this->badgeName.'!',
            'url' => route('achievements.index'),
        ];
    }
}
