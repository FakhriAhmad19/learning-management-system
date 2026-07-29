<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Dua Langkah (2FA) - LMS System</title>
    @vite(['resources/css/app.css'])
    <!-- Alpine.js untuk toggle switch antara Kode Authenticator dan Recovery Code -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-xl shadow-md p-8 border border-gray-200" x-data="{ recovery: false }">

        <!-- Header -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-indigo-100 text-indigo-600 mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-gray-800">Verifikasi Dua Langkah</h2>

            <!-- Petunjuk Mode Normal -->
            <p class="text-sm text-gray-500 mt-1" x-show="!recovery">
                Buka aplikasi authenticator kamu dan masukkan kode 6-digit yang ditampilkan.
            </p>

            <!-- Petunjuk Mode Recovery -->
            <p class="text-sm text-gray-500 mt-1" x-show="recovery" style="display: none;">
                Masukkan salah satu kode pemulihan darurat (recovery code) kamu.
            </p>
        </div>

        <!-- Validation Errors -->
        @if ($errors->any())
            <div class="mb-4 p-3 bg-red-100 text-red-700 text-sm rounded-lg">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Form Fortify 2FA Challenge -->
        <form action="{{ route('two-factor.login') }}" method="POST" class="space-y-4">
            @csrf

            <!-- Input 1: Kode Authenticator 6 Digit -->
            <div x-show="!recovery">
                <label for="code" class="block text-sm font-medium text-gray-700 mb-1">Kode Otentikasi</label>
                <input type="text" id="code" name="code" inputmode="numeric" autofocus autocomplete="one-time-code"
                    placeholder="123456"
                    class="w-full px-4 py-2 border rounded-lg text-center tracking-widest text-lg font-mono focus:ring-2 focus:ring-indigo-500 focus:outline-none @error('code') border-red-500 @enderror">
            </div>

            <!-- Input 2: Recovery Code Darurat -->
            <div x-show="recovery" style="display: none;">
                <label for="recovery_code" class="block text-sm font-medium text-gray-700 mb-1">Kode Pemulihan (Recovery Code)</label>
                <input type="text" id="recovery_code" name="recovery_code" autocomplete="off"
                    placeholder="xxxx-xxxx-xxxx"
                    class="w-full px-4 py-2 border rounded-lg font-mono focus:ring-2 focus:ring-indigo-500 focus:outline-none @error('recovery_code') border-red-500 @enderror">
            </div>

            <!-- Submit Button -->
            <button type="submit"
                class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg shadow transition duration-200">
                Verifikasi & Masuk
            </button>
        </form>

        <!-- Toggle Switch antara Mode Authenticator & Recovery Code -->
        <div class="text-center mt-6 pt-4 border-t border-gray-100">
            <button type="button" class="text-xs text-indigo-600 font-semibold hover:underline"
                x-show="!recovery"
                @click="recovery = true; $nextTick(() => { document.getElementById('recovery_code').focus() })">
                Gunakan Kode Pemulihan Darurat
            </button>

            <button type="button" class="text-xs text-indigo-600 font-semibold hover:underline"
                x-show="recovery"
                style="display: none;"
                @click="recovery = false; $nextTick(() => { document.getElementById('code').focus() })">
                Gunakan Aplikasi Authenticator
            </button>
        </div>

    </div>
</body>
</html>
