<?php

namespace App\Notifications;

use App\Models\Assignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AssignmentPublished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Assignment $assignment) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $course = $this->assignment->module->course;

        $mail = (new MailMessage)
            ->subject('Tugas baru: '.$this->assignment->title)
            ->greeting('Halo '.$notifiable->name.'!')
            ->line('Ada tugas baru di kelas "'.$course->title.'".')
            ->line('Tugas: '.$this->assignment->title);

        if ($this->assignment->due_date) {
            $mail->line('Tenggat pengumpulan: '.$this->assignment->due_date->format('d M Y, H:i'));
        }

        return $mail
            ->action('Kerjakan Tugas', route('assignment.show', [$course->slug, $this->assignment->id]))
            ->line('Selamat mengerjakan!');
    }

    public function toArray(object $notifiable): array
    {
        $course = $this->assignment->module->course;

        return [
            'type' => 'assignment_published',
            'title' => 'Tugas baru di '.$course->title,
            'message' => $this->assignment->title,
            'url' => route('assignment.show', [$course->slug, $this->assignment->id]),
        ];
    }
}
