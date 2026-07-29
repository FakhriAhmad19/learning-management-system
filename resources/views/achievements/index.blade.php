<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pencapaian Saya - LMS Platform</title>
    @vite(['resources/css/app.css'])
</head>

<body class="bg-gray-50 text-gray-800 font-sans antialiased">

    <nav class="bg-white border-b border-gray-100 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="{{ route('home') }}" class="text-xl font-bold text-indigo-600">LearningSystem</a>
            <div class="flex items-center space-x-4 text-sm font-medium">
                <a href="{{ route('home') }}" class="text-gray-600 hover:text-indigo-600">Katalog</a>
                <a href="{{ route('my-courses') }}" class="text-gray-600 hover:text-indigo-600">Kelas Saya</a>
                <a href="{{ route('grades.index') }}" class="text-gray-600 hover:text-indigo-600">Nilai Saya</a>
                <a href="{{ route('achievements.index') }}" class="text-indigo-600">Pencapaian</a>
                <a href="{{ route('profile.edit') }}" class="text-gray-600 hover:text-indigo-600">Profil</a>
                <x-notification-bell />
                <form action="{{ route('logout') }}" method="POST">@csrf
                    <button class="text-gray-600 hover:text-red-600">Keluar</button>
                </form>
            </div>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-8">
        <!-- Total poin -->
        <div class="bg-indigo-600 text-white rounded-xl p-6 flex items-center justify-between">
            <div>
                <p class="text-indigo-200 text-sm">Total Poin Kamu</p>
                <p class="text-4xl font-extrabold mt-1">{{ number_format($totalPoints, 0, ',', '.') }}</p>
            </div>
            <span class="text-5xl">⭐</span>
        </div>

        <!-- Koleksi badge -->
        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="font-bold text-gray-900 mb-4">
                Koleksi Badge
                <span class="text-sm font-normal text-gray-400">
                    ({{ $badges->whereNotNull('earned_at')->count() }} dari {{ $badges->count() }})
                </span>
            </h2>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                @foreach ($badges as $item)
                    @php $earned = $item['earned_at'] !== null; @endphp
                    <div class="rounded-lg border p-4 text-center {{ $earned ? 'border-indigo-200 bg-indigo-50/50' : 'border-gray-200 bg-gray-50' }}">
                        <span class="text-3xl block {{ $earned ? '' : 'grayscale opacity-40' }}">{{ $item['badge']->icon() }}</span>
                        <p class="font-semibold text-sm mt-2 {{ $earned ? 'text-gray-900' : 'text-gray-400' }}">
                            {{ $item['badge']->name() }}
                        </p>
                        <p class="text-xs mt-1 {{ $earned ? 'text-gray-600' : 'text-gray-400' }}">
                            {{ $item['badge']->description() }}
                        </p>
                        @if ($earned)
                            <p class="text-xs text-indigo-600 font-medium mt-2">
                                Diraih {{ $item['earned_at']->format('d M Y') }}
                            </p>
                        @else
                            <p class="text-xs text-gray-400 mt-2">Belum diraih</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        <!-- Poin per kursus -->
        <section class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <h2 class="font-bold text-gray-900 px-6 py-4 border-b border-gray-100">Poin per Kelas</h2>

            @forelse ($courses as $row)
                <div class="px-6 py-4 border-b border-gray-50 last:border-0 flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="font-medium text-gray-900">{{ $row['course']->title }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            @if ($row['rank'])
                                Peringkat #{{ $row['rank'] }} di kelas ini
                            @else
                                Belum ada poin
                            @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-4 shrink-0">
                        <span class="font-bold text-indigo-600">{{ $row['points'] }} poin</span>
                        <a href="{{ route('leaderboard.show', $row['course']->slug) }}"
                            class="text-sm text-indigo-600 hover:underline">Peringkat</a>
                    </div>
                </div>
            @empty
                <p class="px-6 py-12 text-center text-gray-500">
                    Kamu belum mengikuti kelas apa pun.
                    <a href="{{ route('home') }}" class="text-indigo-600 font-medium hover:underline">Jelajahi katalog</a>
                </p>
            @endforelse
        </section>

        <!-- Riwayat poin terakhir -->
        @if ($recent->isNotEmpty())
            <section class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <h2 class="font-bold text-gray-900 px-6 py-4 border-b border-gray-100">Poin Terakhir</h2>
                @foreach ($recent as $award)
                    <div class="px-6 py-3 border-b border-gray-50 last:border-0 flex items-center justify-between gap-4 text-sm">
                        <div class="min-w-0">
                            <p class="text-gray-800">{{ $award->label() }}</p>
                            <p class="text-xs text-gray-400">
                                {{ $award->course->title ?? '-' }} · {{ $award->created_at->diffForHumans() }}
                            </p>
                        </div>
                        <span class="shrink-0 font-semibold text-green-600">+{{ $award->points }}</span>
                    </div>
                @endforeach
            </section>
        @endif
    </main>

</body>

</html>
