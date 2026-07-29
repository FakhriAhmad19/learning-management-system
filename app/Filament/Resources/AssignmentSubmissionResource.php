<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssignmentSubmissionResource\Pages;
use App\Models\AssignmentSubmission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class AssignmentSubmissionResource extends Resource
{
    protected static ?string $model = AssignmentSubmission::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'LMS Management';

    protected static ?string $navigationLabel = 'Penilaian Tugas';

    protected static ?string $modelLabel = 'Pengumpulan Tugas';

    protected static ?string $pluralModelLabel = 'Pengumpulan Tugas';

    protected static ?int $navigationSort = 5;

    /**
     * Instructor hanya melihat pengumpulan pada kursus miliknya sendiri.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! auth()->user()?->hasRole('Admin')) {
            $query->whereHas(
                'assignment.module.course',
                fn (Builder $q) => $q->where('instructor_id', auth()->id())
            );
        }

        return $query;
    }

    /**
     * Jumlah tugas yang menunggu dinilai — tampil sebagai badge di navigasi.
     */
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
        return $form
            ->schema([
                Forms\Components\Section::make('Jawaban Siswa')
                    ->schema([
                        Forms\Components\Placeholder::make('student_name')
                            ->label('Siswa')
                            ->content(fn (AssignmentSubmission $record) => $record->student->name),

                        Forms\Components\Placeholder::make('assignment_title')
                            ->label('Tugas')
                            ->content(fn (AssignmentSubmission $record) => $record->assignment->title),

                        Forms\Components\Placeholder::make('submitted')
                            ->label('Dikumpulkan')
                            ->content(fn (AssignmentSubmission $record) => $record->submitted_at?->format('d M Y, H:i') ?? '-'),

                        Forms\Components\Placeholder::make('content_view')
                            ->label('Jawaban Teks')
                            ->content(fn (AssignmentSubmission $record) => $record->content ?: '— tidak ada jawaban teks —')
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('attachment_view')
                            ->label('Lampiran')
                            ->content(fn (AssignmentSubmission $record) => $record->attachment
                                ? new HtmlString(
                                    '<a href="'.asset('storage/'.$record->attachment).'" target="_blank" class="text-primary-600 underline">Unduh berkas</a>'
                                )
                                : '— tidak ada lampiran —')
                            ->columnSpanFull(),
                    ])->columns(3),

                Forms\Components\Section::make('Penilaian')
                    ->schema([
                        Forms\Components\TextInput::make('score')
                            ->label('Nilai')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(fn (AssignmentSubmission $record) => $record->assignment->max_score)
                            ->helperText(fn (AssignmentSubmission $record) => 'Maksimum '.$record->assignment->max_score
                                .', batas lulus '.$record->assignment->passing_score)
                            ->required(),

                        Forms\Components\Textarea::make('feedback')
                            ->label('Umpan Balik untuk Siswa')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student.name')->label('Siswa')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('assignment.title')->label('Tugas')->searchable(),
                Tables\Columns\TextColumn::make('assignment.module.course.title')->label('Kursus')->searchable(),
                Tables\Columns\TextColumn::make('submitted_at')->label('Dikumpulkan')->dateTime('d M Y, H:i')->sortable(),
                Tables\Columns\TextColumn::make('score')
                    ->label('Nilai')
                    ->placeholder('Belum dinilai')
                    ->formatStateUsing(fn ($state, AssignmentSubmission $record) => $state.' / '.$record->assignment->max_score),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (AssignmentSubmission $record) => $record->statusLabel())
                    ->color(fn (AssignmentSubmission $record) => match ($record->statusLabel()) {
                        'Lulus' => 'success',
                        'Belum Lulus' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->defaultSort('submitted_at', 'desc')
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
            'index' => Pages\ListAssignmentSubmissions::route('/'),
            'edit' => Pages\EditAssignmentSubmission::route('/{record}/edit'),
        ];
    }
}
