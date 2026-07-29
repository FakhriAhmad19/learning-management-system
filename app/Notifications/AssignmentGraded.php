<?php

namespace App\Notifications;

use App\Models\AssignmentSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AssignmentGraded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AssignmentSubmission $submission) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $assignment = $this->submission->assignment;
        $course = $assignment->module->course;
        $passed = $this->submission->isPassed();

        $mail = (new MailMessage)
            ->subject('Tugas kamu sudah dinilai: '.$assignment->title)
            ->greeting('Halo '.$notifiable->name.'!')
            ->line('Tugas "'.$assignment->title.'" di kelas "'.$course->title.'" sudah dinilai.')
            ->line('Nilai kamu: '.$this->submission->score.' dari '.$assignment->max_score
                .' (batas lulus '.$assignment->passing_score.').')
            ->line($passed
                ? 'Selamat, kamu LULUS tugas ini!'
                : 'Sayangnya nilai kamu belum mencapai batas lulus.');

        if ($this->submission->feedback) {
            $mail->line('Umpan balik pengajar: '.$this->submission->feedback);
        }

        return $mail->action('Lihat Detail Tugas', route('assignment.show', [$course->slug, $assignment->id]));
    }

    public function toArray(object $notifiable): array
    {
        $assignment = $this->submission->assignment;
        $course = $assignment->module->course;

        return [
            'type' => 'assignment_graded',
            'title' => 'Tugas dinilai: '.$assignment->title,
            'message' => 'Nilai '.$this->submission->score.'/'.$assignment->max_score
                .' — '.$this->submission->statusLabel(),
            'url' => route('assignment.show', [$course->slug, $assignment->id]),
        ];
    }
}
