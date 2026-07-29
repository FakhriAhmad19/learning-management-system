<?php

namespace App\Filament\Resources\QuizAttemptResource\Pages;

use App\Filament\Resources\QuizAttemptResource;
use App\Models\Enrollment;
use App\Models\Question;
use App\Models\QuizAttempt;
use App\Notifications\QuizGraded;
use Filament\Resources\Pages\EditRecord;

class EditQuizAttempt extends EditRecord
{
    protected static string $resource = QuizAttemptResource::class;

    /**
     * Isi form dengan nilai esai yang sudah pernah diberikan (bila ada).
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['grades'] = $this->record->answerGrades
            ->mapWithKeys(fn ($grade) => [
                $grade->question_id => [
                    'score' => $grade->score,
                    'feedback' => $grade->feedback,
                ],
            ])
            ->all();

        return $data;
    }

    /**
     * `grades` bukan kolom pada quiz_attempts — simpan terpisah di afterSave().
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->essayGrades = $data['grades'] ?? [];
        unset($data['grades']);

        return $data;
    }

    /** @var array<int, array{score: mixed, feedback: ?string}> */
    protected array $essayGrades = [];

    protected function afterSave(): void
    {
        /** @var QuizAttempt $attempt */
        $attempt = $this->record;
        $wasPending = $attempt->graded_at === null;

        $essayIds = $attempt->quiz->questions()
            ->where('type', Question::TYPE_ESSAY)
            ->pluck('points', 'id');

        foreach ($this->essayGrades as $questionId => $values) {
            if (! $essayIds->has((int) $questionId)) {
                continue;
            }

            $attempt->answerGrades()->updateOrCreate(
                ['question_id' => (int) $questionId],
                [
                    // Jaga-jaga bila batas maksimum di form dilewati
                    'score' => min((int) ($values['score'] ?? 0), $essayIds[(int) $questionId]),
                    'feedback' => $values['feedback'] ?? null,
                    'graded_by' => auth()->id(),
                ]
            );
        }

        // Nilai akhir = bagian beropsi + poin esai yang baru diberikan
        $attempt->refresh()->recalculateScore();

        // Kelulusan kuis memengaruhi progres kursus siswa
        Enrollment::where('user_id', $attempt->user_id)
            ->where('course_id', $attempt->quiz->module->course_id)
            ->first()
            ?->recalculateProgress();

        if ($wasPending && $attempt->isFullyGraded()) {
            $attempt->user->notify(new QuizGraded($attempt));
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
