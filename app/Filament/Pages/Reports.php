<?php

namespace App\Filament\Pages;

use App\Services\ReportService;
use App\Support\Csv;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Reports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'LMS Management';

    protected static ?string $navigationLabel = 'Laporan';

    protected static ?string $title = 'Laporan';

    protected static ?int $navigationSort = 8;

    protected static string $view = 'filament.pages.reports';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['Admin', 'Instructor']) ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportSummary')
                ->label('Ekspor Ringkasan (CSV)')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => $this->exportSummary()),
        ];
    }

    /**
     * Ringkasan seluruh kursus yang boleh dilihat pengguna ini.
     */
    public function getSummariesProperty(): Collection
    {
        return app(ReportService::class)->courseSummaries($this->instructorScope());
    }

    /**
     * Angka gabungan untuk kartu di bagian atas halaman.
     *
     * @return array<string, int>
     */
    public function getTotalsProperty(): array
    {
        $summaries = $this->summaries;

        $students = (int) $summaries->sum('students');
        $completed = (int) $summaries->sum('completed');

        return [
            'courses' => $summaries->count(),
            'students' => $students,
            'completed' => $completed,
            'completion_rate' => $students > 0 ? (int) round($completed / $students * 100) : 0,
            'pending_grading' => (int) $summaries->sum('pending_grading'),
        ];
    }

    public function exportSummary(): StreamedResponse
    {
        $rows = $this->summaries->map(fn (array $row) => [
            $row['course']->title,
            $row['course']->category->name ?? '',
            $row['course']->instructor->name ?? '',
            $row['course']->status,
            $row['students'],
            $row['active'],
            $row['completed'],
            $row['average_progress'],
            $row['completion_rate'],
            $row['pending_grading'],
        ]);

        return Csv::download(
            Csv::filename('ringkasan-kursus'),
            [
                'Kursus', 'Kategori', 'Pengajar', 'Status',
                'Jumlah Siswa', 'Aktif', 'Selesai',
                'Rata-rata Progres (%)', 'Tingkat Penyelesaian (%)', 'Menunggu Dinilai',
            ],
            $rows
        );
    }

    /**
     * null untuk Admin (semua kursus), id sendiri untuk Instructor.
     */
    private function instructorScope(): ?int
    {
        return auth()->user()?->hasRole('Admin') ? null : auth()->id();
    }
}
