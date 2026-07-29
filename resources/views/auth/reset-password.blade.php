<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atur Ulang Password - Learning System</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-xl shadow-lg p-8">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-slate-800">Atur Ulang Password</h1>
            <p class="text-sm text-slate-500 mt-1">Silakan buat password baru untuk akun Anda</p>
        </div>

        <!-- Error Validation -->
        @if ($errors->any())
            <div class="mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-lg text-sm">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Form password baru (Fortify: route 'password.update') -->
        <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
            @csrf

            <!-- Token reset dari tautan email -->
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div>
                <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email', $request->email) }}" required autofocus
                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Password Baru</label>
                <input type="password" id="password" name="password" required
                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-1">Konfirmasi Password Baru</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required
                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
            </div>

            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 rounded-lg transition duration-200">
                Simpan Password Baru
            </button>
        </form>

        <p class="text-center text-sm text-slate-600 mt-6">
            <a href="{{ route('login') }}" class="text-indigo-600 font-medium hover:underline">Kembali ke halaman masuk</a>
        </p>
    </div>
</body>
</html>
