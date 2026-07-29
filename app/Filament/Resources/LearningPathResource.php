<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LearningPathResource\Pages;
use App\Models\LearningPath;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class LearningPathResource extends Resource
{
    protected static ?string $model = LearningPath::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'LMS Management';

    protected static ?string $navigationLabel = 'Jalur Belajar';

    protected static ?string $modelLabel = 'Jalur Belajar';

    protected static ?string $pluralModelLabel = 'Jalur Belajar';

    protected static ?int $navigationSort = 9;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informasi Jalur')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Nama Jalur')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null
                        ),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->disabled()
                        ->dehydrated()
                        ->maxLength(255),

                    Forms\Components\Textarea::make('description')
                        ->label('Deskripsi')
                        ->rows(3)
                        ->columnSpanFull(),

                    Forms\Components\Select::make('status')
                        ->options([
                            'draft' => 'Draft',
                            'published' => 'Published',
                        ])
                        ->default('draft')
                        ->required(),
                ])->columns(2),

            Forms\Components\Section::make('Urutan Kursus')
                ->description('Siswa membuka kursus satu per satu sesuai urutan di bawah.')
                ->schema([
                    Forms\Components\Repeater::make('pathCourses')
                        ->relationship()
                        ->label('')
                        ->schema([
                            Forms\Components\Select::make('course_id')
                                ->label('Kursus')
                                ->relationship('course', 'title')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->distinct()
                                ->columnSpanFull(),
                        ])
                        ->orderColumn('order')
                        ->reorderable()
                        ->defaultItems(1)
                        ->addActionLabel('Tambah Kursus'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('Nama Jalur')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('courses_count')
                    ->counts('courses')
                    ->label('Jumlah Kursus'),
                Tables\Columns\TextColumn::make('students_count')
                    ->counts('students')
                    ->label('Siswa Mengikuti'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'published' ? 'success' : 'warning'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                    ]),
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
            'index' => Pages\ListLearningPaths::route('/'),
            'create' => Pages\CreateLearningPath::route('/create'),
            'edit' => Pages\EditLearningPath::route('/{record}/edit'),
        ];
    }
}
