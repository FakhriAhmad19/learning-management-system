<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi - LMS Platform</title>
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

    <main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Notifikasi</h1>

            @if (auth()->user()->unreadNotifications()->exists())
                <form action="{{ route('notifications.read-all') }}" method="POST">@csrf
                    <button class="text-sm text-indigo-600 font-medium hover:underline">
                        Tandai semua dibaca
                    </button>
                </form>
            @endif
        </div>

        @if (session('success'))
            <div class="px-4 py-3 rounded-lg text-sm border bg-green-50 text-green-800 border-green-200">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100 overflow-hidden">
            @forelse ($notifications as $notification)
                @php $isUnread = $notification->read_at === null; @endphp
                <a href="{{ route('notifications.read', $notification->id) }}"
                    class="flex items-start gap-3 px-5 py-4 hover:bg-gray-50 transition {{ $isUnread ? 'bg-indigo-50/40' : '' }}">
                    <!-- Ikon per jenis notifikasi -->
                    <span class="shrink-0 w-9 h-9 rounded-full flex items-center justify-center text-base
                        @switch($notification->data['type'] ?? '')
                            @case('assignment_published') bg-sky-100 @break
                            @case('assignment_graded') bg-green-100 @break
                            @case('course_completed') bg-amber-100 @break
                            @default bg-gray-100
                        @endswitch">
                        @switch($notification->data['type'] ?? '')
                            @case('assignment_published') 📄 @break
                            @case('assignment_graded') ✅ @break
                            @case('course_completed') 🎉 @break
                            @default 🔔
                        @endswitch
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-gray-900 {{ $isUnread ? '' : 'font-medium' }}">
                            {{ $notification->data['title'] ?? 'Notifikasi' }}
                        </p>
                        <p class="text-sm text-gray-600 mt-0.5">{{ $notification->data['message'] ?? '' }}</p>
                        <p class="text-xs text-gray-400 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                    </div>

                    @if ($isUnread)
                        <span class="shrink-0 mt-2 w-2 h-2 rounded-full bg-indigo-500" title="Belum dibaca"></span>
                    @endif
                </a>
            @empty
                <p class="px-5 py-12 text-center text-gray-500">Belum ada notifikasi.</p>
            @endforelse
        </div>
    </main>

</body>

</html>
