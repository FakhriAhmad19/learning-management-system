<?php

namespace App\Notifications;

use App\Models\QuizAttempt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Memberi tahu siswa bahwa jawaban esainya sudah dinilai dan nilai kuis final.
 */
class QuizGraded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public QuizAttempt $attempt) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $quiz = $this->attempt->quiz;
        $course = $quiz->module->course;

        return (new MailMessage)
            ->subject('Kuis kamu sudah dinilai: '.$quiz->title)
            ->greeting('Halo '.$notifiable->name.'!')
            ->line('Jawaban esai kamu pada kuis "'.$quiz->title.'" sudah dinilai pengajar.')
            ->line('Nilai akhir kamu: '.$this->attempt->score.' (batas lulus '.$quiz->passing_score.').')
            ->line($this->attempt->passed
                ? 'Selamat, kamu LULUS kuis ini!'
                : 'Sayangnya nilai kamu belum mencapai batas lulus.')
            ->action('Lihat Hasil', route('quiz.show', [$course->slug, $quiz->id]));
    }

    public function toArray(object $notifiable): array
    {
        $quiz = $this->attempt->quiz;
        $course = $quiz->module->course;

        return [
            'type' => 'quiz_graded',
            'title' => 'Kuis dinilai: '.$quiz->title,
            'message' => 'Nilai akhir '.$this->attempt->score
                .' — '.($this->attempt->passed ? 'Lulus' : 'Belum Lulus'),
            'url' => route('quiz.show', [$course->slug, $quiz->id]),
        ];
    }
}
