<?php

namespace App\Filament\Widgets;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LmsStatsOverview extends BaseWidget
{
    protected static ?int $sort = -2;

    protected function getStats(): array
    {
        $publishedCourses = Course::where('status', 'published')->count();
        $totalCourses = Course::count();
        $students = User::role('Student')->count();
        $activeEnrollments = Enrollment::where('status', 'active')->count();
        $totalEnrollments = Enrollment::count();
        $revenue = Enrollment::sum('amount_paid');

        return [
            Stat::make('Total Kursus', $totalCourses)
                ->description($publishedCourses.' terpublikasi')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('primary'),

            Stat::make('Jumlah Siswa', $students)
                ->description('Pengguna dengan peran Student')
                ->descriptionIcon('heroicon-m-users')
                ->color('success'),

            Stat::make('Pendaftaran', $totalEnrollments)
                ->description($activeEnrollments.' sedang aktif')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('info'),

            Stat::make('Total Pendapatan', 'Rp '.number_format($revenue, 0, ',', '.'))
                ->description('Akumulasi seluruh pembayaran')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),
        ];
    }
}
