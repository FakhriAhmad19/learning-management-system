<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssignmentResource\Pages;
use App\Models\Assignment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssignmentResource extends Resource
{
    protected static ?string $model = Assignment::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'LMS Management';

    protected static ?string $navigationLabel = 'Tugas';

    protected static ?string $modelLabel = 'Tugas';

    protected static ?string $pluralModelLabel = 'Tugas';

    protected static ?int $navigationSort = 4;

    /**
     * Instructor hanya mengelola tugas pada kursus miliknya sendiri.
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
                Forms\Components\Section::make('Informasi Tugas')
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
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('title')
                            ->label('Judul Tugas')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('description')
                            ->label('Instruksi Tugas')
                            ->columnSpanFull(),

                        Forms\Components\DateTimePicker::make('due_date')
                            ->label('Tenggat Pengumpulan')
                            ->helperText('Kosongkan bila tanpa tenggat'),

                        Forms\Components\TextInput::make('max_score')
                            ->label('Nilai Maksimum')
                            ->numeric()
                            ->minValue(1)
                            ->default(100)
                            ->required(),

                        Forms\Components\TextInput::make('passing_score')
                            ->label('Nilai Minimum Lulus')
                            ->numeric()
                            ->minValue(0)
                            ->default(60)
                            ->required()
                            ->helperText('Tugas dihitung selesai bila nilainya mencapai angka ini'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('module.course.title')->label('Kursus')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('module.title')->label('Modul')->searchable(),
                Tables\Columns\TextColumn::make('title')->label('Judul')->searchable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Tenggat')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('Tanpa tenggat')
                    ->sortable(),
                Tables\Columns\TextColumn::make('submissions_count')
                    ->counts('submissions')
                    ->label('Dikumpulkan'),
                Tables\Columns\TextColumn::make('passing_score')
                    ->label('Batas Lulus')
                    ->formatStateUsing(fn ($state, Assignment $record) => $state.' / '.$record->max_score),
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
            'index' => Pages\ListAssignments::route('/'),
            'create' => Pages\CreateAssignment::route('/create'),
            'edit' => Pages\EditAssignment::route('/{record}/edit'),
        ];
    }
}
