<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CourseResource\Pages;
use App\Models\Course;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CourseResource extends Resource
{
    protected static ?string $model = Course::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'LMS Management';

    /**
     * Instructor hanya melihat & mengelola kursus miliknya sendiri (PRD §3).
     * Admin melihat semua kursus.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! auth()->user()?->hasRole('Admin')) {
            $query->where('instructor_id', auth()->id());
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Utama Kursus')
                    ->schema([
                        // Dropdown Pemilih Pengajar (Instructor)
                        Forms\Components\Select::make('instructor_id')
                            ->relationship('instructor', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->label('Pengajar')
                            // Instructor otomatis jadi pemilik & tidak bisa mengubah pengajar
                            ->default(fn () => auth()->id())
                            ->disabled(fn () => ! auth()->user()?->hasRole('Admin'))
                            ->dehydrated(),

                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->label('Kategori')
                            ->placeholder('Tanpa kategori'),

                        Forms\Components\TextInput::make('title')
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

                        Forms\Components\FileUpload::make('thumbnail')
                            ->image()
                            ->directory('course-thumbnails')
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('about')
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('price')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'archived' => 'Archived',
                            ])
                            ->default('draft')
                            ->required(),
                    ])->columns(2),

                // Inline Repeater: Kelola Modul & Materi sekaligus di halaman Kursus!
                Forms\Components\Section::make('Kurikulum (Modul & Materi)')
                    ->schema([
                        Forms\Components\Repeater::make('modules')
                            ->relationship()
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->label('Nama Modul / Bab')
                                    ->required(),

                                Forms\Components\Repeater::make('lessons')
                                    ->relationship()
                                    ->schema([
                                        Forms\Components\TextInput::make('title')
                                            ->label('Judul Materi')
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('slug', Str::slug($state)))
                                            ->columnSpanFull(),

                                        Forms\Components\Hidden::make('slug')
                                            ->dehydrateStateUsing(fn (?string $state, $get) => $state ?: Str::slug($get('title'))),

                                        Forms\Components\RichEditor::make('content')
                                            ->label('Isi Materi (Teks Bacaan)')
                                            ->columnSpanFull(),

                                        Forms\Components\FileUpload::make('attachment')
                                            ->label('Lampiran Berkas (PDF / Docx / ZIP)')
                                            ->directory('lesson-attachments')
                                            ->acceptedFileTypes([
                                                'application/pdf',
                                                'application/msword',
                                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                                'application/zip',
                                                'application/x-zip-compressed',
                                            ])
                                            ->downloadable()
                                            ->columnSpanFull(),

                                        Forms\Components\Toggle::make('is_free_preview')->label('Gratis Preview'),
                                    ])
                                    ->orderColumn('order')
                                    ->columns(1)
                                    ->grid(1),
                            ])
                            ->orderColumn('order')
                            ->collapsible(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail'),
                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('instructor.name')->label('Pengajar')->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge()
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')->money('IDR')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'draft' => 'warning',
                        'archived' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ]),
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->label('Kategori')
                    ->searchable()
                    ->preload(),
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
            'index' => Pages\ListCourses::route('/'),
            'create' => Pages\CreateCourse::route('/create'),
            'edit' => Pages\EditCourse::route('/{record}/edit'),
        ];
    }
}
