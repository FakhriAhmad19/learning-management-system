<?php

namespace App\Notifications;

use App\Models\Discussion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Memberi tahu penanya bahwa pertanyaannya dibalas.
 */
class DiscussionReplied extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Discussion $discussion) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lesson = $this->discussion->lesson;
        $course = $lesson->module->course;
        $author = $this->discussion->author;

        return (new MailMessage)
            ->subject('Pertanyaan kamu dibalas di '.$course->title)
            ->greeting('Halo '.$notifiable->name.'!')
            ->line($author->name.' membalas pertanyaan kamu di materi "'.$lesson->title.'".')
            ->line('"'.Str::limit($this->discussion->body, 200).'"')
            ->action('Lihat Diskusi', route('learn.show', [$course->slug, $lesson->slug]));
    }

    public function toArray(object $notifiable): array
    {
        $lesson = $this->discussion->lesson;
        $course = $lesson->module->course;

        return [
            'type' => 'discussion_replied',
            'title' => 'Pertanyaan kamu dibalas',
            'message' => $this->discussion->author->name.' di materi "'.$lesson->title.'"',
            'url' => route('learn.show', [$course->slug, $lesson->slug]),
        ];
    }
}
