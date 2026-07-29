<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelas Saya - LMS Platform</title>
    @vite(['resources/css/app.css'])
</head>

<body class="bg-gray-50 text-gray-800 font-sans antialiased">

    <!-- Navigation -->
    <nav class="bg-white border-b border-gray-100 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="{{ route('home') }}" class="text-xl font-bold text-indigo-600">LearningSystem</a>
            <div class="flex items-center space-x-4 text-sm font-medium">
                <a href="{{ route('home') }}" class="text-gray-600 hover:text-indigo-600">Katalog</a>
                <a href="{{ route('my-courses') }}" class="text-indigo-600">Kelas Saya</a>
                <a href="{{ route('grades.index') }}" class="text-gray-600 hover:text-indigo-600">Nilai Saya</a>
                <a href="{{ route('achievements.index') }}" class="text-gray-600 hover:text-indigo-600">Pencapaian</a>
                <a href="{{ route('profile.edit') }}" class="text-gray-600 hover:text-indigo-600">Profil</a>
                <x-notification-bell />
                <form action="{{ route('logout') }}" method="POST">@csrf
                    <button class="text-gray-600 hover:text-red-600">Keluar</button>
                </form>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <h1 class="text-2xl font-bold text-gray-900 mb-8">Kelas Saya</h1>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            @forelse ($enrollments as $enrollment)
                @php $course = $enrollment->course; @endphp
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col">
                    <!-- Thumbnail -->
                    <div class="h-40 bg-indigo-100 relative">
                        @if ($course->thumbnail)
                            <img src="{{ asset('storage/' . $course->thumbnail) }}" alt="{{ $course->title }}" class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-indigo-400 font-bold text-lg px-4 text-center">
                                {{ Str::limit($course->title, 30) }}
                            </div>
                        @endif
                        @if ($enrollment->status === 'completed')
                            <span class="absolute top-3 right-3 px-3 py-1 text-xs font-semibold rounded-full bg-green-500 text-white">Selesai</span>
                        @endif
                    </div>

                    <div class="p-5 flex-1 flex flex-col">
                        <p class="text-xs text-indigo-600 font-semibold uppercase tracking-wider">
                            {{ $course->instructor->name ?? 'Instructor' }}
                        </p>
                        <h3 class="text-lg font-bold text-gray-900 mt-1 mb-3 line-clamp-2">{{ $course->title }}</h3>

                        <!-- Progress -->
                        <div class="mt-auto">
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="text-gray-500">Progres</span>
                                <span class="font-semibold text-indigo-600">{{ $enrollment->progress_percentage }}%</span>
                            </div>
                            <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden mb-4">
                                <div class="h-full bg-indigo-600 rounded-full" style="width: {{ $enrollment->progress_percentage }}%"></div>
                            </div>
                            <a href="{{ route('learn.show', $course->slug) }}"
                                class="block w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg text-center transition">
                                {{ $enrollment->progress_percentage > 0 ? 'Lanjut Belajar' : 'Mulai Belajar' }}
                            </a>
                            @if ($enrollment->status === 'completed')
                                <a href="{{ route('certificate.show', $course->slug) }}"
                                    class="block w-full mt-2 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-lg text-center transition">
                                    🎓 Sertifikat
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-16">
                    <p class="text-gray-500 mb-4">Kamu belum mengikuti kelas apa pun.</p>
                    <a href="{{ route('home') }}" class="inline-block px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg">
                        Jelajahi Katalog Kursus
                    </a>
                </div>
            @endforelse
        </div>
    </main>

</body>

</html>
