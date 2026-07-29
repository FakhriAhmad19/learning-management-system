<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuizResource\Pages;
use App\Models\Question;
use App\Models\Quiz;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QuizResource extends Resource
{
    protected static ?string $model = Quiz::class;

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationGroup = 'LMS Management';

    protected static ?string $navigationLabel = 'Kuis';

    protected static ?string $modelLabel = 'Kuis';

    protected static ?int $navigationSort = 3;

    /**
     * Instructor hanya mengelola kuis pada kursus miliknya sendiri.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! auth()->user()?->hasRole('Admin')) {
            $query->whereHas('module.course', fn (Builder $q) => $q->where('instructor_id', auth()->id()));
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Kuis')
                    ->schema([
                        Forms\Components\Select::make('module_id')
                            ->label('Modul (Bab)')
                            ->relationship(
                                'module',
                                'title',
                                fn (Builder $query) => auth()->user()?->hasRole('Admin')
                                    ? $query->with('course')
                                    : $query->whereHas('course', fn (Builder $q) => $q->where('instructor_id', auth()->id()))->with('course')
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => ($record->course->title ?? '-').' — '.$record->title)
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('title')
                            ->label('Judul Kuis')
                            ->default('Kuis Akhir Bab')
                            ->required(),

                        Forms\Components\TextInput::make('passing_score')
                            ->label('Nilai Minimum Lulus')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->default(70)
                            ->required(),

                        Forms\Components\TextInput::make('max_attempts')
                            ->label('Batas Percobaan')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(255)
                            ->placeholder('Tanpa batas')
                            ->helperText('Kosongkan bila siswa boleh mengulang sebanyak apa pun'),

                        Forms\Components\TextInput::make('time_limit_minutes')
                            ->label('Batas Waktu')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(600)
                            ->suffix('menit')
                            ->placeholder('Tanpa batas')
                            ->helperText('Jawaban terkirim otomatis saat waktu habis'),
                    ])->columns(3),

                Forms\Components\Section::make('Daftar Pertanyaan')
                    ->schema([
                        Forms\Components\Repeater::make('questions')
                            ->relationship()
                            ->label('')
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->label('Tipe Soal')
                                    ->options([
                                        Question::TYPE_MULTIPLE_CHOICE => 'Pilihan Ganda',
                                        Question::TYPE_TRUE_FALSE => 'Benar / Salah',
                                        Question::TYPE_ESSAY => 'Esai (dinilai manual)',
                                    ])
                                    ->default(Question::TYPE_MULTIPLE_CHOICE)
                                    ->required()
                                    ->live()
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('points')
                                    ->label('Bobot Poin')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(100)
                                    ->default(1)
                                    ->required()
                                    ->helperText('Soal berbobot lebih besar berpengaruh lebih besar pada nilai')
                                    ->columnSpan(1),

                                Forms\Components\Textarea::make('question')
                                    ->label('Pertanyaan')
                                    ->required()
                                    ->rows(2)
                                    ->columnSpanFull(),

                                // Benar/Salah: opsinya dibuat otomatis oleh model Question
                                Forms\Components\Radio::make('true_false_answer')
                                    ->label('Jawaban Benar')
                                    ->options([
                                        'benar' => 'Benar',
                                        'salah' => 'Salah',
                                    ])
                                    ->default('benar')
                                    ->inline()
                                    ->required()
                                    ->visible(fn (Forms\Get $get) => $get('type') === Question::TYPE_TRUE_FALSE)
                                    ->columnSpanFull(),

                                Forms\Components\Placeholder::make('essay_hint')
                                    ->label('')
                                    ->content('Soal esai dijawab dengan teks bebas dan dinilai manual lewat menu "Penilaian Esai". Kuis yang memuat esai tidak langsung dinyatakan lulus sebelum dinilai.')
                                    ->visible(fn (Forms\Get $get) => $get('type') === Question::TYPE_ESSAY)
                                    ->columnSpanFull(),

                                Forms\Components\Repeater::make('options')
                                    ->relationship()
                                    ->label('Pilihan Jawaban (centang yang benar)')
                                    ->schema([
                                        Forms\Components\TextInput::make('option_text')
                                            ->label('Teks Jawaban')
                                            ->required(),
                                        Forms\Components\Toggle::make('is_correct')
                                            ->label('Jawaban Benar')
                                            ->inline(false),
                                    ])
                                    ->columns(2)
                                    ->minItems(2)
                                    ->defaultItems(2)
                                    ->visible(fn (Forms\Get $get) => $get('type') === Question::TYPE_MULTIPLE_CHOICE)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->orderColumn('order')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['question'] ?? 'Pertanyaan baru')
                            ->defaultItems(1)
                            ->addActionLabel('Tambah Pertanyaan'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('module.course.title')->label('Kursus')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('module.title')->label('Modul')->searchable(),
                Tables\Columns\TextColumn::make('title')->label('Judul')->searchable(),
                Tables\Columns\TextColumn::make('questions_count')->counts('questions')->label('Jml Soal'),
                Tables\Columns\TextColumn::make('passing_score')->label('Batas Lulus')->formatStateUsing(fn ($state) => $state.'%'),
                Tables\Columns\TextColumn::make('max_attempts')->label('Batas Percobaan')->placeholder('Tanpa batas'),
                Tables\Columns\TextColumn::make('time_limit_minutes')
                    ->label('Batas Waktu')
                    ->placeholder('Tanpa batas')
                    ->formatStateUsing(fn ($state) => $state.' menit'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuizzes::route('/'),
            'create' => Pages\CreateQuiz::route('/create'),
            'edit' => Pages\EditQuiz::route('/{record}/edit'),
        ];
    }
}
