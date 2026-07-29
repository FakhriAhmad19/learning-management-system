<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - LMS Platform</title>
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
                <x-notification-bell />
                <a href="{{ route('profile.edit') }}" class="text-indigo-600">Profil</a>
                <form action="{{ route('logout') }}" method="POST">@csrf
                    <button class="text-gray-600 hover:text-red-600">Keluar</button>
                </form>
            </div>
        </div>
    </nav>

    <main class="max-w-2xl mx-auto px-4 py-12 space-y-8">
        <h1 class="text-2xl font-bold text-gray-900">Profil Saya</h1>

        @if (session('status') === 'profile-information-updated')
            <div class="bg-green-50 text-green-800 border border-green-200 px-4 py-3 rounded-lg text-sm">Informasi profil berhasil diperbarui.</div>
        @endif
        @if (session('status') === 'password-updated')
            <div class="bg-green-50 text-green-800 border border-green-200 px-4 py-3 rounded-lg text-sm">Kata sandi berhasil diperbarui.</div>
        @endif

        <!-- Informasi Profil -->
        <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-1">Informasi Akun</h2>
            <p class="text-sm text-gray-500 mb-5">Perbarui nama dan alamat email kamu.</p>

            <form action="{{ route('user-profile-information.update') }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm px-3 py-2 border">
                    @error('name')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm px-3 py-2 border">
                    @error('email')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg">
                    Simpan Perubahan
                </button>
            </form>
        </section>

        <!-- Ubah Password -->
        <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-1">Ubah Kata Sandi</h2>
            <p class="text-sm text-gray-500 mb-5">Gunakan kata sandi yang panjang dan acak agar tetap aman.</p>

            <form action="{{ route('user-password.update') }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kata Sandi Saat Ini</label>
                    <input type="password" name="current_password"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm px-3 py-2 border">
                    @error('current_password', 'updatePassword')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kata Sandi Baru</label>
                    <input type="password" name="password"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm px-3 py-2 border">
                    @error('password', 'updatePassword')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Konfirmasi Kata Sandi Baru</label>
                    <input type="password" name="password_confirmation"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm px-3 py-2 border">
                </div>
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg">
                    Perbarui Kata Sandi
                </button>
            </form>
        </section>

        <!-- Autentikasi Dua Faktor (2FA) -->
        <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-1">
                <h2 class="text-lg font-semibold text-gray-900">Autentikasi Dua Faktor (2FA)</h2>
                @if ($user->two_factor_secret && $user->two_factor_confirmed_at)
                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">Aktif</span>
                @elseif ($user->two_factor_secret)
                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">Menunggu Konfirmasi</span>
                @else
                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">Nonaktif</span>
                @endif
            </div>
            <p class="text-sm text-gray-500 mb-5">Tambahkan lapisan keamanan ekstra dengan aplikasi authenticator (Google Authenticator, Authy, dsb.).</p>

            @if (session('status') === 'two-factor-authentication-enabled')
                <div class="mb-4 bg-blue-50 text-blue-800 border border-blue-200 px-4 py-3 rounded-lg text-sm">2FA diaktifkan. Pindai QR di bawah lalu masukkan kode untuk mengonfirmasi.</div>
            @elseif (session('status') === 'two-factor-authentication-confirmed')
                <div class="mb-4 bg-green-50 text-green-800 border border-green-200 px-4 py-3 rounded-lg text-sm">2FA berhasil diaktifkan &amp; dikonfirmasi.</div>
            @elseif (session('status') === 'two-factor-authentication-disabled')
                <div class="mb-4 bg-amber-50 text-amber-800 border border-amber-200 px-4 py-3 rounded-lg text-sm">2FA telah dinonaktifkan.</div>
            @elseif (session('status') === 'recovery-codes-generated')
                <div class="mb-4 bg-blue-50 text-blue-800 border border-blue-200 px-4 py-3 rounded-lg text-sm">Kode pemulihan baru telah dibuat.</div>
            @endif

            @if (! $user->two_factor_secret)
                {{-- Belum aktif --}}
                <form action="{{ route('two-factor.enable') }}" method="POST">
                    @csrf
                    <button class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg">Aktifkan 2FA</button>
                </form>
            @elseif (! $user->two_factor_confirmed_at)
                {{-- Menunggu konfirmasi: QR + input kode --}}
                <p class="text-sm text-gray-600 mb-3">1. Pindai kode QR ini dengan aplikasi authenticator kamu:</p>
                <div class="inline-block p-3 bg-white border rounded-lg mb-4">{!! $user->twoFactorQrCodeSvg() !!}</div>

                <form action="{{ route('two-factor.confirm') }}" method="POST" class="space-y-3 max-w-xs">
                    @csrf
                    <label class="block text-sm font-medium text-gray-700">2. Masukkan kode dari aplikasi</label>
                    <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456"
                        class="w-full rounded-lg border-gray-300 border px-3 py-2 text-sm tracking-[0.3em]">
                    @error('code')<p class="text-red-600 text-xs">{{ $message }}</p>@enderror
                    <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg">Konfirmasi &amp; Aktifkan</button>
                </form>

                <form action="{{ route('two-factor.disable') }}" method="POST" class="mt-3">
                    @csrf @method('DELETE')
                    <button class="text-sm text-gray-500 hover:text-red-600">Batalkan</button>
                </form>
            @else
                {{-- Aktif & terkonfirmasi: kode pemulihan + nonaktifkan --}}
                <p class="text-sm text-gray-600 mb-2">Simpan kode pemulihan berikut di tempat aman. Tiap kode hanya dapat digunakan sekali bila kamu kehilangan akses ke perangkat authenticator.</p>
                <div class="bg-gray-50 border rounded-lg p-4 grid grid-cols-2 gap-x-6 gap-y-1 font-mono text-sm text-gray-700 max-w-md">
                    @foreach ($user->recoveryCodes() as $code)
                        <div>{{ $code }}</div>
                    @endforeach
                </div>
                <div class="flex flex-wrap gap-2 mt-4">
                    <form action="{{ route('two-factor.regenerate-recovery-codes') }}" method="POST">
                        @csrf
                        <button class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg">Buat Ulang Kode Pemulihan</button>
                    </form>
                    <form action="{{ route('two-factor.disable') }}" method="POST">
                        @csrf @method('DELETE')
                        <button class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg">Nonaktifkan 2FA</button>
                    </form>
                </div>
            @endif
        </section>
    </main>

</body>

</html>
