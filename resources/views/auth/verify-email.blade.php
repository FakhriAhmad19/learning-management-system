<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Email - LMS Platform</title>
    @vite(['resources/css/app.css'])
</head>

<body class="bg-gray-50 text-gray-800 font-sans antialiased min-h-screen flex items-center justify-center px-4">

    <div class="w-full max-w-md bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
        <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-indigo-100 flex items-center justify-center text-2xl">✉️</div>
        <h1 class="text-xl font-bold text-gray-900 mb-2">Verifikasi Alamat Email</h1>
        <p class="text-sm text-gray-600 mb-6">
            Terima kasih telah mendaftar! Sebelum mulai belajar, silakan verifikasi email kamu dengan
            mengeklik tautan yang telah kami kirim. Belum menerima email?
        </p>

        @if (session('status') === 'verification-link-sent')
            <div class="mb-4 bg-green-50 text-green-800 border border-green-200 px-4 py-3 rounded-lg text-sm">
                Tautan verifikasi baru telah dikirim ke alamat email kamu.
            </div>
        @endif

        <form action="{{ route('verification.send') }}" method="POST">
            @csrf
            <button type="submit"
                class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow transition">
                Kirim Ulang Email Verifikasi
            </button>
        </form>

        <form action="{{ route('logout') }}" method="POST" class="mt-3">
            @csrf
            <button type="submit" class="text-sm text-gray-500 hover:text-red-600">Keluar</button>
        </form>
    </div>

</body>

</html>
