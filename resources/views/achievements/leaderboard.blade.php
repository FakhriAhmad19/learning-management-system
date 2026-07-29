<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peringkat — {{ $course->title }}</title>
    @vite(['resources/css/app.css'])
</head>

<body class="bg-gray-50 text-gray-800 font-sans antialiased">

    <nav class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-3xl mx-auto px-4 h-14 flex items-center justify-between">
            <a href="{{ route('learn.show', $course->slug) }}" class="text-sm text-indigo-600 font-semibold hover:text-indigo-800">
                ← Kembali ke Ruang Belajar
            </a>
            <a href="{{ route('achievements.index') }}" class="text-sm text-gray-600 hover:text-indigo-600">Pencapaian Saya</a>
        </div>
    </nav>

    <main class="max-w-3xl mx-auto px-4 py-8 space-y-6">
        <div>
            <p class="text-xs text-indigo-600 font-semibold uppercase tracking-wider">Papan Peringkat</p>
            <h1 class="text-2xl font-extrabold text-gray-900">{{ $course->title }}</h1>
            <p class="text-sm text-gray-500 mt-1">Peringkat dihitung dari poin yang diperoleh di kelas ini saja.</p>
        </div>

        <!-- Posisi saya -->
        <div class="bg-indigo-600 text-white rounded-xl p-5 flex items-center justify-between">
            <div>
                <p class="text-indigo-200 text-sm">Posisi kamu</p>
                <p class="text-2xl font-extrabold mt-0.5">
                    {{ $myRank ? '#' . $myRank : 'Belum berperingkat' }}
                </p>
            </div>
            <span class="text-lg font-bold">{{ $myPoints }} poin</span>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            @forelse ($entries as $entry)
                @php $isMe = $entry['user']?->is(auth()->user()); @endphp
                <div class="px-5 py-3 border-b border-gray-50 last:border-0 flex items-center gap-4 {{ $isMe ? 'bg-indigo-50/60' : '' }}">
                    <span class="w-8 shrink-0 text-center font-bold {{ $entry['rank'] <= 3 ? 'text-lg' : 'text-gray-400 text-sm' }}">
                        @switch($entry['rank'])
                            @case(1) 🥇 @break
                            @case(2) 🥈 @break
                            @case(3) 🥉 @break
                            @default {{ $entry['rank'] }}
                        @endswitch
                    </span>

                    <p class="flex-1 min-w-0 font-medium text-gray-900 truncate">
                        {{ $entry['user']?->name ?? 'Pengguna' }}
                        @if ($isMe)
                            <span class="ml-1 text-xs font-semibold px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700">Kamu</span>
                        @endif
                    </p>

                    <span class="shrink-0 font-bold text-indigo-600">{{ $entry['points'] }}</span>
                </div>
            @empty
                <p class="px-5 py-12 text-center text-gray-500">
                    Belum ada yang mengumpulkan poin di kelas ini. Jadilah yang pertama!
                </p>
            @endforelse
        </div>
    </main>

</body>

</html>
