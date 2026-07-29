<?php

namespace App\Notifications;

use App\Models\Discussion;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Memberi tahu pengajar bahwa ada pertanyaan baru di materinya.
 * Lonceng panel saja — pengajar tidak dibanjiri email tiap satu pertanyaan.
 */
class QuestionAsked extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Discussion $discussion) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $lesson = $this->discussion->lesson;
        $course = $lesson->module->course;

        return FilamentNotification::make()
            ->title('Pertanyaan baru dari siswa')
            ->body($this->discussion->author->name.' bertanya di materi "'.$lesson->title.'"')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->iconColor('info')
            ->actions([
                Action::make('lihat')
                    ->label('Lihat pertanyaan')
                    ->url(route('learn.show', [$course->slug, $lesson->slug]))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
