<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $course->title }} - LMS Platform</title>
    @vite(['resources/css/app.css'])
</head>

<body class="bg-gray-50 text-gray-800 font-sans">

    <!-- Nav Minimalis -->
    <nav class="bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="{{ route('home') }}" class="text-indigo-600 font-bold">← Kembali ke Katalog</a>
        </div>
    </nav>

    <!-- Flash Messages -->
    @if (session('success') || session('info') || session('error'))
        @php
            $flashType = session('success') ? 'success' : (session('info') ? 'info' : 'error');
            $flashText = session('success') ?? session('info') ?? session('error');
            $flashColor = ['success' => 'bg-green-50 text-green-800 border-green-200', 'info' => 'bg-amber-50 text-amber-800 border-amber-200', 'error' => 'bg-red-50 text-red-800 border-red-200'][$flashType];
        @endphp
        <div class="max-w-5xl mx-auto px-4 pt-4">
            <div class="border {{ $flashColor }} px-4 py-3 rounded-lg text-sm">{{ $flashText }}</div>
        </div>
    @endif

    <!-- Detail Header -->
    <div class="bg-gray-900 text-white py-12 px-4">
        <div class="max-w-5xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-8 items-center">
            <div class="md:col-span-2 space-y-4">
                <span
                    class="text-xs bg-green-500 text-white px-2.5 py-1 rounded-full uppercase tracking-wider font-semibold">
                    Kelas Gratis
                </span>
                <h1 class="text-3xl font-extrabold">{{ $course->title }}</h1>
                <p class="text-gray-300 text-sm">{{ $course->about }}</p>
                <p class="text-xs text-gray-400">Pengajar: <strong
                        class="text-white">{{ $course->instructor->name }}</strong></p>
            </div>

            <!-- Card Pendaftaran -->
            <div class="bg-white text-gray-800 p-6 rounded-xl shadow-lg border">
                <div class="text-2xl font-bold mb-4 text-green-600">
                    GRATIS
                </div>

                @guest
                    {{-- Belum login --}}
                    <a href="{{ route('login') }}"
                        class="block w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg shadow text-center transition">
                        Masuk untuk Ikuti Kelas
                    </a>
                @else
                    @if ($enrollment && in_array($enrollment->status, ['active', 'completed']))
                        {{-- Sudah terdaftar & aktif --}}
                        <a href="{{ route('learn.show', $course->slug) }}"
                            class="block w-full py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg shadow text-center transition">
                            Masuk Ruang Belajar →
                        </a>
                    @else
                        {{-- Belum terdaftar — semua kelas akses gratis, langsung aktif --}}
                        <form action="{{ route('courses.enroll', $course->id) }}" method="POST">
                            @csrf
                            <button type="submit"
                                class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg shadow text-center transition">
                                Ikuti Kelas Sekarang
                            </button>
                        </form>
                    @endif
                @endguest
            </div>
        </div>
    </div>

    <!-- Kurikulum / Silabus Materi -->
    <main class="max-w-5xl mx-auto px-4 py-12">
        <h2 class="text-xl font-bold text-gray-900 mb-6">Kurikulum Pembelajaran</h2>

        <div class="space-y-4">
            @foreach ($course->modules as $module)
                <div class="bg-white border rounded-xl overflow-hidden shadow-sm">
                    <div class="bg-gray-100 px-5 py-3 font-semibold text-gray-800 border-b">
                        {{ $module->title }}
                    </div>
                    <ul class="divide-y divide-gray-100">
                        @foreach ($module->lessons as $lesson)
                            <li class="px-5 py-3 flex items-center justify-between text-sm">
                                <span class="text-gray-700">{{ $lesson->title }}</span>
                                @if ($lesson->is_free_preview)
                                    <span
                                        class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded font-medium">Preview
                                        Gratis</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </main>

</body>

</html>
