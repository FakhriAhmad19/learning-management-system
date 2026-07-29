<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sertifikat - {{ $course->title }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            @page { size: A4 landscape; margin: 0; }
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen py-8 px-4 font-serif">

    <!-- Toolbar (tidak ikut tercetak) -->
    <div class="no-print max-w-4xl mx-auto mb-6 flex items-center justify-between font-sans">
        <a href="{{ route('my-courses') }}" class="text-sm text-indigo-600 font-semibold hover:text-indigo-800">← Kelas Saya</a>
        <button onclick="window.print()"
            class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow">
            Cetak / Simpan PDF
        </button>
    </div>

    <!-- Sertifikat -->
    <div class="max-w-4xl mx-auto bg-white shadow-xl">
        <div class="m-4 border-4 border-double border-amber-500 p-10 sm:p-16 text-center relative">
            <!-- Sudut dekoratif -->
            <div class="absolute top-3 left-3 w-10 h-10 border-t-4 border-l-4 border-amber-400"></div>
            <div class="absolute top-3 right-3 w-10 h-10 border-t-4 border-r-4 border-amber-400"></div>
            <div class="absolute bottom-3 left-3 w-10 h-10 border-b-4 border-l-4 border-amber-400"></div>
            <div class="absolute bottom-3 right-3 w-10 h-10 border-b-4 border-r-4 border-amber-400"></div>

            <p class="text-sm uppercase tracking-[0.3em] text-gray-500 mb-2">Sertifikat Kelulusan</p>
            <div class="w-20 h-0.5 bg-amber-500 mx-auto mb-8"></div>

            <p class="text-gray-600 mb-2">Diberikan kepada</p>
            <h1 class="text-4xl sm:text-5xl font-bold text-gray-900 mb-6">{{ $user->name }}</h1>

            <p class="text-gray-600 max-w-2xl mx-auto leading-relaxed">
                atas keberhasilannya menyelesaikan seluruh materi pembelajaran dalam kursus
            </p>
            <h2 class="text-2xl font-semibold text-indigo-700 my-4">{{ $course->title }}</h2>

            <div class="flex items-end justify-between mt-14 pt-6 max-w-2xl mx-auto text-sm">
                <div class="text-center">
                    <p class="font-semibold text-gray-800 border-t border-gray-400 pt-2 px-6">
                        {{ optional($enrollment->completed_at)->translatedFormat('d F Y') ?? now()->translatedFormat('d F Y') }}
                    </p>
                    <p class="text-gray-500 text-xs mt-1">Tanggal Kelulusan</p>
                </div>
                <div class="text-4xl text-amber-500">&#10004;</div>
                <div class="text-center">
                    <p class="font-semibold text-gray-800 border-t border-gray-400 pt-2 px-6">{{ $course->instructor->name }}</p>
                    <p class="text-gray-500 text-xs mt-1">Pengajar</p>
                </div>
            </div>

            <p class="text-[10px] text-gray-400 mt-10 font-sans">
                LearningSystem • ID Sertifikat: LS-{{ str_pad($enrollment->id, 6, '0', STR_PAD_LEFT) }}
            </p>
        </div>
    </div>

</body>

</html>
