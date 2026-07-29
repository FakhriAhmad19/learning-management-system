<?php

namespace App\Filament\Pages;

use App\Models\Course;
use App\Services\ReportService;
use App\Support\Csv;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Gradebook extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationGroup = 'LMS Management';

    protected static ?string $navigationLabel = 'Buku Nilai';

    protected static ?string $title = 'Buku Nilai';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.gradebook';

    public ?int $courseId = null;

    /**
     * Halaman ini mengikuti hak akses kursus: Admin & Instructor boleh melihat,
     * dan daftar kursusnya dibatasi ke milik masing-masing instruktur.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['Admin', 'Instructor']) ?? false;
    }

    public function mount(): void
    {
        $this->courseId = $this->courseOptions()->keys()->first();
        $this->form->fill(['courseId' => $this->courseId]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('courseId')
                ->label('Pilih Kursus')
                ->options(fn () => $this->courseOptions())
                ->searchable()
                ->live()
                ->placeholder('Belum ada kursus'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportGradebook')
                ->label('Ekspor Nilai (CSV)')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn () => $this->courseId !== null)
                ->action(fn () => $this->exportGradebook()),

            Action::make('exportParticipants')
                ->label('Ekspor Peserta (CSV)')
                ->icon('heroicon-o-users')
                ->color('gray')
                ->visible(fn () => $this->courseId !== null)
                ->action(fn () => $this->exportParticipants()),
        ];
    }

    /**
     * Matriks nilai persis seperti yang tampil di layar.
     */
    public function exportGradebook(): ?StreamedResponse
    {
        $course = $this->currentCourse();
        if ($course === null) {
            return null;
        }

        $reports = app(ReportService::class);
        $columns = $reports->assessmentColumns($course);

        $header = collect(['Nama', 'Email'])
            ->concat($columns->map(fn (array $c) => $c['title'].' ('.($c['type'] === 'quiz' ? 'Kuis' : 'Tugas').', maks '.$c['max'].')'))
            ->concat(['Progres (%)', 'Status'])
            ->all();

        $rows = $reports->gradebookRows($course)->map(function (array $row) use ($columns) {
            $line = [$row['student']->name, $row['student']->email];

            foreach ($columns as $column) {
                $cell = $row['cells'][$column['key']];
                $line[] = match (true) {
                    $cell['pending'] => 'Perlu dinilai',
                    $cell['score'] === null => '',
                    default => $cell['score'],
                };
            }

            $line[] = $row['progress'];
            $line[] = $row['status'] === 'completed' ? 'Selesai' : 'Aktif';

            return $line;
        });

        return Csv::download(Csv::filename('nilai-'.$course->slug), $header, $rows);
    }

    /**
     * Daftar peserta beserta progres dan tanggal penting.
     */
    public function exportParticipants(): ?StreamedResponse
    {
        $course = $this->currentCourse();
        if ($course === null) {
            return null;
        }

        $rows = app(ReportService::class)->participants($course)->map(fn ($enrollment) => [
            $enrollment->student->name,
            $enrollment->student->email,
            $enrollment->status === 'completed' ? 'Selesai' : 'Aktif',
            $enrollment->progress_percentage,
            $enrollment->created_at?->format('Y-m-d'),
            $enrollment->completed_at?->format('Y-m-d') ?? '',
        ]);

        return Csv::download(
            Csv::filename('peserta-'.$course->slug),
            ['Nama', 'Email', 'Status', 'Progres (%)', 'Tanggal Daftar', 'Tanggal Selesai'],
            $rows
        );
    }

    protected function currentCourse(): ?Course
    {
        if ($this->courseId === null) {
            return null;
        }

        // Ambil lewat daftar yang boleh dilihat agar tidak bisa menembus kursus orang lain
        return $this->courseOptions()->keys()->contains($this->courseId)
            ? Course::find($this->courseId)
            : null;
    }

    /**
     * Kursus yang boleh dilihat: seluruhnya untuk Admin, miliknya untuk Instructor.
     */
    protected function courseOptions(): Collection
    {
        $instructorId = auth()->user()?->hasRole('Admin') ? null : auth()->id();

        return app(ReportService::class)
            ->visibleCourses($instructorId)
            ->pluck('title', 'id');
    }

    /**
     * Kolom penilaian kursus terpilih: seluruh kuis lalu seluruh tugas.
     */
    public function getColumnsProperty(): Collection
    {
        $course = $this->currentCourse();

        return $course === null
            ? collect()
            : app(ReportService::class)->assessmentColumns($course);
    }

    /**
     * Satu baris per siswa terdaftar, berisi nilai untuk setiap kolom penilaian.
     */
    public function getRowsProperty(): Collection
    {
        $course = $this->currentCourse();

        return $course === null
            ? collect()
            : app(ReportService::class)->gradebookRows($course);
    }
}
