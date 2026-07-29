<?php

namespace App\Notifications;

use App\Models\Course;
use App\Models\LearningPath;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Memberi tahu siswa bahwa kursus berikutnya di jalurnya sudah terbuka —
 * momen paling memotivasi untuk lanjut, jadi dikirim in-app sekaligus email.
 */
class PathCourseUnlocked extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public LearningPath $path,
        public Course $course,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kursus berikutnya terbuka: '.$this->course->title)
            ->greeting('Halo '.$notifiable->name.'!')
            ->line('Kamu menyelesaikan tahap sebelumnya di jalur "'.$this->path->title.'".')
            ->line('Kursus berikutnya sudah terbuka: '.$this->course->title)
            ->action('Lanjutkan Jalur', route('paths.show', $this->path->slug));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'path_course_unlocked',
            'title' => 'Kursus berikutnya terbuka',
            'message' => $this->course->title.' di jalur '.$this->path->title,
            'url' => route('paths.show', $this->path->slug),
        ];
    }
}
