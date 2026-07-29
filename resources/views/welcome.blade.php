<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS Platform - Belajar Skill Digital Terbaru</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased">

    <!-- Navigation -->
    <nav class="bg-white border-b border-gray-100 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="{{ route('home') }}" class="text-xl font-bold text-indigo-600">LearningSystem</a>
            <div class="flex items-center space-x-4 text-sm font-medium">
                <a href="{{ route('paths.index') }}" class="text-gray-600 hover:text-indigo-600">Jalur Belajar</a>
                @auth
                    <a href="{{ route('my-courses') }}" class="text-gray-600 hover:text-indigo-600">Kelas Saya</a>
                    <a href="{{ route('grades.index') }}" class="text-gray-600 hover:text-indigo-600">Nilai Saya</a>
                    <a href="{{ route('achievements.index') }}" class="text-gray-600 hover:text-indigo-600">Pencapaian</a>
                    <a href="{{ route('profile.edit') }}" class="text-gray-600 hover:text-indigo-600">Profil</a>
                    @if (auth()->user()->hasAnyRole(['Admin', 'Instructor']))
                        <a href="{{ url('/admin') }}" class="text-gray-700 hover:text-indigo-600">Dashboard</a>
                    @endif
                    <x-notification-bell />
                    <form action="{{ route('logout') }}" method="POST">@csrf
                        <button class="text-gray-600 hover:text-red-600">Keluar</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="text-gray-600 hover:text-indigo-600">Masuk</a>
                    <a href="{{ route('register') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow">Daftar</a>
                @endauth
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="bg-indigo-900 text-white py-16 px-4">
        <div class="max-w-4xl mx-auto text-center space-y-4">
            <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight">Kuasai Skill Digital Bersama Para Ahli</h1>
            <p class="text-indigo-200 text-lg max-w-2xl mx-auto">Tingkatkan karier kamu dengan mengikuti kursus pemrograman, ilmu data, dan teknologi terkini secara fleksibel.</p>

            <!-- Pencarian Kursus -->
            <form method="GET" action="{{ route('home') }}" class="max-w-xl mx-auto pt-4 flex gap-2">
                @if ($activeCategory)
                    <input type="hidden" name="kategori" value="{{ $activeCategory->slug }}">
                @endif
                <input type="search" name="q" value="{{ $search }}"
                    placeholder="Cari kursus, misal: Laravel"
                    class="flex-1 px-4 py-2.5 rounded-lg text-gray-900 bg-white border border-transparent focus:ring-2 focus:ring-indigo-400 outline-none">
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg transition">
                    Cari
                </button>
            </form>
        </div>
    </header>

    <!-- Catalog Section -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <!-- Filter Kategori -->
        @if ($categories->isNotEmpty())
            <div class="flex flex-wrap gap-2 mb-8">
                <a href="{{ route('home', array_filter(['q' => $search])) }}"
                    class="px-4 py-1.5 text-sm font-medium rounded-full border transition {{ $activeCategory ? 'bg-white text-gray-600 border-gray-200 hover:border-indigo-300' : 'bg-indigo-600 text-white border-indigo-600' }}">
                    Semua
                </a>
                @foreach ($categories as $category)
                    <a href="{{ route('home', array_filter(['q' => $search, 'kategori' => $category->slug])) }}"
                        class="px-4 py-1.5 text-sm font-medium rounded-full border transition {{ $activeCategory?->id === $category->id ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:border-indigo-300' }}">
                        {{ $category->name }}
                    </a>
                @endforeach
            </div>
        @endif

        <div class="flex items-baseline justify-between mb-8">
            <h2 class="text-2xl font-bold text-gray-900">
                @if ($search !== '' || $activeCategory)
                    Hasil Pencarian
                @else
                    Daftar Kursus Populer
                @endif
            </h2>
            <span class="text-sm text-gray-500">{{ $courses->count() }} kursus ditemukan</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            @forelse ($courses as $course)
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition flex flex-col justify-between">
                    <div>
                        <!-- Thumbnail -->
                        <div class="h-48 bg-gray-200 relative">
                            @if($course->thumbnail)
                                <img src="{{ asset('storage/' . $course->thumbnail) }}" alt="{{ $course->title }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center bg-indigo-100 text-indigo-400 font-bold text-xl">
                                    {{ Str::limit($course->title, 15) }}
                                </div>
                            @endif

                            <!-- Badge Akses Gratis (semua kelas gratis) -->
                            <span class="absolute top-3 right-3 px-3 py-1 text-xs font-semibold rounded-full bg-green-500 text-white">
                                Gratis
                            </span>
                        </div>

                        <!-- Card Body -->
                        <div class="p-5 space-y-3">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs text-indigo-600 font-semibold uppercase tracking-wider">
                                    {{ $course->instructor->name ?? 'Instructor' }}
                                </p>
                                @if ($course->category)
                                    <span class="shrink-0 px-2 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-600">
                                        {{ $course->category->name }}
                                    </span>
                                @endif
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 line-clamp-1">
                                <a href="{{ route('courses.show', $course->slug) }}" class="hover:text-indigo-600">
                                    {{ $course->title }}
                                </a>
                            </h3>
                            <p class="text-sm text-gray-600 line-clamp-2">
                                {{ $course->about }}
                            </p>
                        </div>
                    </div>

                    <!-- Card Footer -->
                    <div class="p-5 pt-0 border-t border-gray-50 mt-4 flex items-center justify-between text-xs text-gray-500">
                        <span>{{ $course->modules->count() }} Modul</span>
                        <span>{{ $course->enrollments_count }} Siswa</span>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-12 text-gray-500">
                    @if ($search !== '' || $activeCategory)
                        Tidak ada kursus yang cocok dengan pencarian Anda.
                        <a href="{{ route('home') }}" class="text-indigo-600 font-medium hover:underline">Lihat semua kursus</a>
                    @else
                        Belum ada kursus yang dipublikasikan.
                    @endif
                </div>
            @endforelse
        </div>
    </main>

</body>
</html>
