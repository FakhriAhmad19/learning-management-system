<?php

namespace App\Filament\Resources\AssignmentSubmissionResource\Pages;

use App\Filament\Resources\AssignmentSubmissionResource;
use Filament\Resources\Pages\EditRecord;

class EditAssignmentSubmission extends EditRecord
{
    protected static string $resource = AssignmentSubmissionResource::class;

    /**
     * Menyimpan form ini berarti menilai: catat waktu & penilainya.
     * Progres kursus siswa diperbarui otomatis oleh model AssignmentSubmission.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['graded_at'] = now();
        $data['graded_by'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
