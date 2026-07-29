<?php

namespace App\Notifications;

use App\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CourseCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Course $course) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Selamat! Kamu menyelesaikan '.$this->course->title)
            ->greeting('Selamat '.$notifiable->name.'! 🎉')
            ->line('Kamu telah menyelesaikan seluruh materi, kuis, dan tugas di kelas "'.$this->course->title.'".')
            ->action('Unduh Sertifikat', route('certificate.show', $this->course->slug))
            ->line('Terus semangat belajar!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'course_completed',
            'title' => 'Kelas selesai: '.$this->course->title,
            'message' => 'Sertifikat kamu sudah bisa diunduh.',
            'url' => route('certificate.show', $this->course->slug),
        ];
    }
}
