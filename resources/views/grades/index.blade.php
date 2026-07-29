<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nilai Saya - LMS Platform</title>
    @vite(['resources/css/app.css'])
</head>

<body class="bg-gray-50 text-gray-800 font-sans antialiased">

    <!-- Navigation -->
    <nav class="bg-white border-b border-gray-100 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="{{ route('home') }}" class="text-xl font-bold text-indigo-600">LearningSystem</a>
            <div class="flex items-center space-x-4 text-sm font-medium">
                <a href="{{ route('home') }}" class="text-gray-600 hover:text-indigo-600">Katalog</a>
                <a href="{{ route('my-courses') }}" class="text-gray-600 hover:text-indigo-600">Kelas Saya</a>
                <a href="{{ route('grades.index') }}" class="text-indigo-600">Nilai Saya</a>
                <a href="{{ route('achievements.index') }}" class="text-gray-600 hover:text-indigo-600">Pencapaian</a>
                <a href="{{ route('profile.edit') }}" class="text-gray-600 hover:text-indigo-600">Profil</a>
                <x-notification-bell />
                <form action="{{ route('logout') }}" method="POST">@csrf
                    <button class="text-gray-600 hover:text-red-600">Keluar</button>
                </form>
            </div>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-8">
        <h1 class="text-2xl font-bold text-gray-900">Nilai Saya</h1>

        @forelse ($report as $item)
            @php $enrollment = $item['enrollment']; @endphp
            <section class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <!-- Header kursus -->
                <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-bold text-gray-900">{{ $enrollment->course->title }}</h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Pengajar: {{ $enrollment->course->instructor->name ?? '-' }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-gray-500">Progres {{ $enrollment->progress_percentage }}%</span>
                        @if ($enrollment->status === 'completed')
                            <a href="{{ route('certificate.show', $enrollment->course->slug) }}"
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-green-100 text-green-700 hover:bg-green-200 transition">
                                Lihat Sertifikat
                            </a>
                        @endif
                    </div>
                </div>

                <!-- Tabel nilai -->
                @if ($item['rows']->isEmpty())
                    <p class="px-5 py-6 text-sm text-gray-500">Kelas ini belum memiliki kuis atau tugas bernilai.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                                <tr>
                                    <th class="text-left font-semibold px-5 py-3">Penilaian</th>
                                    <th class="text-left font-semibold px-5 py-3">Jenis</th>
                                    <th class="text-right font-semibold px-5 py-3">Nilai</th>
                                    <th class="text-right font-semibold px-5 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($item['rows'] as $row)
                                    <tr>
                                        <td class="px-5 py-3">
                                            <p class="font-medium text-gray-900">{{ $row['title'] }}</p>
                                            <p class="text-xs text-gray-400">{{ $row['module'] }}</p>
                                        </td>
                                        <td class="px-5 py-3 text-gray-600">{{ $row['type'] }}</td>
                                        <td class="px-5 py-3 text-right font-semibold text-gray-900">
                                            @if ($row['score'] === null)
                                                <span class="text-gray-300">—</span>
                                            @else
                                                {{ $row['score'] }}<span class="text-gray-400 font-normal"> / {{ $row['max'] }}</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-right">
                                            <span class="px-2.5 py-1 text-xs font-semibold rounded-full
                                                @if ($row['status'] === 'Lulus') bg-green-100 text-green-700
                                                @elseif ($row['status'] === 'Belum Lulus') bg-red-100 text-red-700
                                                @elseif ($row['status'] === 'Menunggu Penilaian') bg-amber-100 text-amber-700
                                                @else bg-gray-100 text-gray-500 @endif">
                                                {{ $row['status'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @empty
            <div class="bg-white rounded-xl border border-gray-200 px-5 py-12 text-center text-gray-500">
                Kamu belum mengikuti kelas apa pun.
                <a href="{{ route('home') }}" class="text-indigo-600 font-medium hover:underline">Jelajahi katalog</a>
            </div>
        @endforelse
    </main>

</body>

</html>
