<?php

namespace App\Notifications;

use App\Filament\Resources\QuizAttemptResource;
use App\Models\QuizAttempt;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Memberi tahu pengajar bahwa ada jawaban esai yang menunggu dinilai.
 * Lonceng panel saja, konsisten dengan notifikasi pengumpulan tugas.
 */
class QuizNeedsReview extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public QuizAttempt $attempt) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $quiz = $this->attempt->quiz;

        return FilamentNotification::make()
            ->title('Jawaban esai perlu dinilai')
            ->body($this->attempt->user->name.' mengerjakan "'.$quiz->title.'"')
            ->icon('heroicon-o-pencil-square')
            ->iconColor('warning')
            ->actions([
                Action::make('nilai')
                    ->label('Nilai sekarang')
                    ->url(QuizAttemptResource::getUrl('edit', ['record' => $this->attempt->id]))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
