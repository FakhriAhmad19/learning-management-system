<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $path->title }} - Jalur Belajar</title>
    @vite(['resources/css/app.css'])
</head>

<body class="bg-gray-50 text-gray-800 font-sans antialiased">

    <nav class="bg-white border-b border-gray-100 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="{{ route('home') }}" class="text-xl font-bold text-indigo-600">LearningSystem</a>
            <div class="flex items-center space-x-4 text-sm font-medium">
                <a href="{{ route('home') }}" class="text-gray-600 hover:text-indigo-600">Katalog</a>
                <a href="{{ route('paths.index') }}" class="text-indigo-600">Jalur Belajar</a>
                @auth
                    <a href="{{ route('my-courses') }}" class="text-gray-600 hover:text-indigo-600">Kelas Saya</a>
                    <a href="{{ route('achievements.index') }}" class="text-gray-600 hover:text-indigo-600">Pencapaian</a>
                    <x-notification-bell />
                @else
                    <a href="{{ route('login') }}" class="text-gray-600 hover:text-indigo-600">Masuk</a>
                @endauth
            </div>
        </div>
    </nav>

    <main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-6">
        @if (session('success'))
            <div class="px-4 py-3 rounded-lg text-sm border bg-green-50 text-green-800 border-green-200">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="px-4 py-3 rounded-lg text-sm border bg-red-50 text-red-800 border-red-200">
                {{ session('error') }}
            </div>
        @endif

        <!-- Judul & aksi -->
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h1 class="text-2xl font-extrabold text-gray-900">{{ $path->title }}</h1>
            <p class="text-gray-600 mt-2">{{ $path->description }}</p>

            @auth
                @if ($joined)
                    <div class="mt-4">
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="text-gray-500">Progres jalur</span>
                            <span class="font-semibold text-indigo-600">{{ $progress }}%</span>
                        </div>
                        <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div class="h-full bg-indigo-600 rounded-full" style="width: {{ $progress }}%"></div>
                        </div>
                    </div>

                    <form action="{{ route('paths.leave', $path->id) }}" method="POST" class="mt-4"
                        onsubmit="return confirm('Keluar dari jalur ini? Progres kursusmu tetap tersimpan.')">
                        @csrf @method('DELETE')
                        <button class="text-sm text-gray-500 hover:text-red-600">Keluar dari jalur ini</button>
                    </form>
                @else
                    <form action="{{ route('paths.join', $path->id) }}" method="POST" class="mt-5">
                        @csrf
                        <button class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg shadow transition">
                            Ikuti Jalur Ini
                        </button>
                    </form>
                    <p class="text-xs text-gray-500 mt-2">
                        Setelah bergabung, kursus terbuka satu per satu sesuai urutan.
                    </p>
                @endif
            @else
                <a href="{{ route('login') }}"
                    class="inline-block mt-5 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg shadow">
                    Masuk untuk mengikuti
                </a>
            @endauth
        </div>

        <!-- Urutan kursus -->
        <div class="space-y-3">
            @forelse ($steps as $step)
                @php
                    $course = $step['course'];
                    // Kursus yang sudah diselesaikan tidak pernah tampil terkunci,
                    // meski siswa baru bergabung ke jalur setelah menyelesaikannya.
                    $locked = ! $step['unlocked'] && ! $step['completed'];
                @endphp
                <div class="bg-white rounded-xl border p-5 flex items-start gap-4 {{ $locked ? 'border-gray-200 opacity-70' : 'border-gray-200' }}">
                    <!-- Nomor urut / status -->
                    <span class="shrink-0 w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold
                        {{ $step['completed'] ? 'bg-green-100 text-green-700' : ($locked ? 'bg-gray-100 text-gray-400' : 'bg-indigo-100 text-indigo-700') }}">
                        @if ($step['completed'])
                            ✓
                        @elseif ($locked)
                            🔒
                        @else
                            {{ $step['number'] }}
                        @endif
                    </span>

                    <div class="min-w-0 flex-1">
                        <h3 class="font-bold text-gray-900">
                            @if ($locked)
                                {{ $course->title }}
                            @else
                                <a href="{{ route('courses.show', $course->slug) }}" class="hover:text-indigo-600">
                                    {{ $course->title }}
                                </a>
                            @endif
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $course->instructor->name ?? 'Pengajar' }}
                            @if ($step['enrollment'] && ! $step['completed'])
                                · Progres {{ $step['enrollment']->progress_percentage }}%
                            @endif
                        </p>

                        @if ($locked)
                            <p class="text-xs text-gray-400 mt-2">
                                Terkunci — selesaikan kursus sebelumnya untuk membuka.
                            </p>
                        @endif
                    </div>

                    <!-- Aksi -->
                    <div class="shrink-0">
                        @if ($step['completed'])
                            <span class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-green-100 text-green-700">Selesai</span>
                        @elseif ($locked)
                            <span class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-gray-100 text-gray-400">Terkunci</span>
                        @elseif ($step['enrollment'])
                            <a href="{{ route('learn.show', $course->slug) }}"
                                class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                                Lanjutkan
                            </a>
                        @else
                            <a href="{{ route('courses.show', $course->slug) }}"
                                class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                                Mulai
                            </a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-xl border border-gray-200 px-5 py-12 text-center text-gray-500">
                    Jalur ini belum memiliki kursus.
                </div>
            @endforelse
        </div>
    </main>

</body>

</html>
