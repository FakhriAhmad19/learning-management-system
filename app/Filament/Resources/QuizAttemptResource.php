<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuizAttemptResource\Pages;
use App\Models\Question;
use App\Models\QuizAttempt;
use Filament\Forms;
use Filament\Forms\Components\Component;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QuizAttemptResource extends Resource
{
    protected static ?string $model = QuizAttempt::class;

    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';

    protected static ?string $navigationGroup = 'LMS Management';

    protected static ?string $navigationLabel = 'Penilaian Esai';

    protected static ?string $modelLabel = 'Pengerjaan Kuis';

    protected static ?string $pluralModelLabel = 'Pengerjaan Kuis';

    protected static ?int $navigationSort = 7;

    /**
     * Hanya pengerjaan yang sudah dikirim DAN memuat soal esai yang relevan
     * di sini — kuis pilihan ganda murni tidak perlu dinilai manual.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->completed()
            ->where('expired', false)
            ->whereHas('quiz.questions', fn (Builder $q) => $q->where('type', Question::TYPE_ESSAY));

        if (! auth()->user()?->hasRole('Admin')) {
            $query->whereHas(
                'quiz.module.course',
                fn (Builder $q) => $q->where('instructor_id', auth()->id())
            );
        }

        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = static::getEloquentQuery()->whereNull('graded_at')->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Pengerjaan')
                ->schema([
                    Forms\Components\Placeholder::make('student')
                        ->label('Siswa')
                        ->content(fn (QuizAttempt $record) => $record->user->name),

                    Forms\Components\Placeholder::make('quiz_title')
                        ->label('Kuis')
                        ->content(fn (QuizAttempt $record) => $record->quiz->title),

                    Forms\Components\Placeholder::make('submitted')
                        ->label('Dikirim')
                        ->content(fn (QuizAttempt $record) => $record->completed_at?->format('d M Y, H:i') ?? '-'),
                ])->columns(3),

            // Satu blok per soal esai: jawaban siswa + input nilai & catatan
            Forms\Components\Section::make('Jawaban Esai')
                ->schema(fn (QuizAttempt $record) => static::essayFields($record)),
        ]);
    }

    /**
     * Bangun field penilaian untuk setiap soal esai pada kuis ini.
     *
     * @return array<Component>
     */
    protected static function essayFields(QuizAttempt $record): array
    {
        $essays = $record->quiz->questions()->where('type', Question::TYPE_ESSAY)->orderBy('order')->get();

        return $essays->flatMap(function (Question $question) use ($record) {
            $answer = $record->essayAnswerFor($question);

            return [
                Forms\Components\Placeholder::make("q{$question->id}_question")
                    ->label('Pertanyaan')
                    ->content($question->question)
                    ->columnSpanFull(),

                Forms\Components\Placeholder::make("q{$question->id}_answer")
                    ->label('Jawaban Siswa')
                    ->content($answer ?? '— tidak dijawab —')
                    ->columnSpanFull(),

                Forms\Components\TextInput::make("grades.{$question->id}.score")
                    ->label('Nilai (maks '.$question->points.')')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue($question->points)
                    ->required(),

                Forms\Components\Textarea::make("grades.{$question->id}.feedback")
                    ->label('Catatan untuk Siswa')
                    ->rows(2),
            ];
        })->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Siswa')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('quiz.title')->label('Kuis')->searchable(),
                Tables\Columns\TextColumn::make('quiz.module.course.title')->label('Kursus')->searchable(),
                Tables\Columns\TextColumn::make('completed_at')->label('Dikirim')->dateTime('d M Y, H:i')->sortable(),
                Tables\Columns\TextColumn::make('score')->label('Nilai'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (QuizAttempt $record) => match (true) {
                        $record->needsReview() => 'Menunggu Penilaian',
                        $record->passed => 'Lulus',
                        default => 'Belum Lulus',
                    })
                    ->color(fn (QuizAttempt $record) => match (true) {
                        $record->needsReview() => 'warning',
                        $record->passed => 'success',
                        default => 'danger',
                    }),
            ])
            ->defaultSort('completed_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('belum_dinilai')
                    ->label('Belum dinilai')
                    ->query(fn (Builder $query) => $query->whereNull('graded_at'))
                    ->default(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Nilai'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuizAttempts::route('/'),
            'edit' => Pages\EditQuizAttempt::route('/{record}/edit'),
        ];
    }
}
