<?php

namespace App\Notifications;

use App\Filament\Resources\AssignmentSubmissionResource;
use App\Models\AssignmentSubmission;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Memberi tahu pengajar bahwa ada tugas baru yang perlu dinilai.
 * Hanya lewat lonceng panel Filament — pengajar tidak dibanjiri email
 * setiap kali seorang siswa mengumpulkan tugas.
 */
class SubmissionReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AssignmentSubmission $submission) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $assignment = $this->submission->assignment;

        return FilamentNotification::make()
            ->title('Tugas baru perlu dinilai')
            ->body($this->submission->student->name.' mengumpulkan "'.$assignment->title.'"')
            ->icon('heroicon-o-inbox-arrow-down')
            ->iconColor('warning')
            ->actions([
                Action::make('nilai')
                    ->label('Nilai sekarang')
                    ->url(AssignmentSubmissionResource::getUrl('edit', ['record' => $this->submission->id]))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
