<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jalur Belajar - LMS Platform</title>
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
                    <form action="{{ route('logout') }}" method="POST">@csrf
                        <button class="text-gray-600 hover:text-red-600">Keluar</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="text-gray-600 hover:text-indigo-600">Masuk</a>
                    <a href="{{ route('register') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg shadow">Daftar</a>
                @endauth
            </div>
        </div>
    </nav>

    <header class="bg-indigo-900 text-white py-12 px-4">
        <div class="max-w-4xl mx-auto text-center space-y-3">
            <h1 class="text-3xl sm:text-4xl font-extrabold">Jalur Belajar Terarah</h1>
            <p class="text-indigo-200 max-w-2xl mx-auto">
                Rangkaian kursus yang disusun berurutan. Selesaikan satu kursus untuk membuka kursus berikutnya.
            </p>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @forelse ($paths as $path)
                <div class="bg-white rounded-xl border border-gray-200 p-6 flex flex-col">
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="text-lg font-bold text-gray-900">
                            <a href="{{ route('paths.show', $path->slug) }}" class="hover:text-indigo-600">
                                {{ $path->title }}
                            </a>
                        </h2>
                        @if ($joinedIds->contains($path->id))
                            <span class="shrink-0 text-xs font-semibold px-2 py-0.5 rounded-full bg-green-100 text-green-700">
                                Diikuti
                            </span>
                        @endif
                    </div>

                    <p class="text-sm text-gray-600 mt-2 line-clamp-3 flex-1">{{ $path->description }}</p>

                    <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-50 text-sm">
                        <span class="text-gray-500">{{ $path->courses->count() }} kursus</span>
                        <a href="{{ route('paths.show', $path->slug) }}" class="text-indigo-600 font-medium hover:underline">
                            Lihat jalur →
                        </a>
                    </div>
                </div>
            @empty
                <div class="md:col-span-2 text-center py-16 text-gray-500">
                    Belum ada jalur belajar yang dipublikasikan.
                </div>
            @endforelse
        </div>
    </main>

</body>

</html>
