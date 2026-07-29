@auth
    @php $unreadCount = auth()->user()->unreadNotifications()->count(); @endphp
    <a href="{{ route('notifications.index') }}" title="Notifikasi"
        class="relative inline-flex items-center text-gray-600 hover:text-indigo-600 transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M14.857 17.082a23.85 23.85 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
        </svg>
        @if ($unreadCount > 0)
            <span class="absolute -top-1.5 -right-2 min-w-4 h-4 px-1 flex items-center justify-center rounded-full bg-red-500 text-white text-[10px] font-bold">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </a>
@endauth
