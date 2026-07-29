<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $assignment->title }} — {{ $course->title }}</title>
    @vite(['resources/css/app.css'])
</head>

<body class="bg-gray-100 text-gray-800 font-sans">

    <!-- Top Bar -->
    <nav class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-3xl mx-auto px-4 h-14 flex items-center justify-between">
            <a href="{{ route('learn.show', $course->slug) }}" class="text-sm text-indigo-600 font-semibold hover:text-indigo-800">
                ← Kembali ke Ruang Belajar
            </a>
            <span class="text-xs text-gray-500">
                Batas lulus: {{ $assignment->passing_score }} / {{ $assignment->max_score }}
            </span>
        </div>
    </nav>

    <main class="max-w-3xl mx-auto px-4 py-8 space-y-6">

        <div>
            <p class="text-xs text-indigo-600 font-semibold uppercase tracking-wider">{{ $assignment->module->title }}</p>
            <h1 class="text-2xl font-extrabold text-gray-900">{{ $assignment->title }}</h1>
            @if ($assignment->due_date)
                <p class="text-sm mt-1 {{ $assignment->isOverdue() ? 'text-red-600 font-medium' : 'text-gray-500' }}">
                    Tenggat: {{ $assignment->due_date->format('d M Y, H:i') }}
                    @if ($assignment->isOverdue())
                        (telah lewat)
                    @endif
                </p>
            @endif
        </div>

        @if (session('success'))
            <div class="px-4 py-3 rounded-lg text-sm border bg-green-50 text-green-800 border-green-200">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="px-4 py-3 rounded-lg text-sm border bg-red-50 text-red-800 border-red-200">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="px-4 py-3 rounded-lg text-sm border bg-red-50 text-red-800 border-red-200">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Instruksi Tugas -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Instruksi</h2>
            <div class="max-w-none text-gray-700 leading-relaxed space-y-3
                [&_h2]:text-lg [&_h2]:font-bold [&_h2]:text-gray-900 [&_h2]:mt-4
                [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:list-decimal [&_ol]:pl-6
                [&_code]:bg-gray-100 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:rounded [&_code]:text-sm [&_code]:text-pink-600
                [&_a]:text-indigo-600 [&_a]:underline">
                {!! $assignment->description ?: '<p class="text-gray-400">Belum ada instruksi.</p>' !!}
            </div>
        </div>

        <!-- Status & Nilai -->
        @if ($submission)
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Status pengumpulan</p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            Dikumpulkan {{ $submission->submitted_at->format('d M Y, H:i') }}
                        </p>
                    </div>
                    <span class="px-4 py-1.5 rounded-full text-sm font-semibold
                        {{ ! $submission->isGraded() ? 'bg-amber-100 text-amber-700' : ($submission->isPassed() ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700') }}">
                        {{ $submission->statusLabel() }}
                    </span>
                </div>

                @if ($submission->isGraded())
                    <div class="border-t border-gray-100 pt-4 flex items-baseline gap-2">
                        <span class="text-sm text-gray-500">Nilai:</span>
                        <span class="text-3xl font-extrabold {{ $submission->isPassed() ? 'text-green-600' : 'text-red-500' }}">
                            {{ $submission->score }}
                        </span>
                        <span class="text-sm text-gray-400">/ {{ $assignment->max_score }}</span>
                    </div>

                    @if ($submission->feedback)
                        <div class="border-t border-gray-100 pt-4">
                            <p class="text-sm font-semibold text-gray-700 mb-1">Umpan balik pengajar</p>
                            <p class="text-sm text-gray-600 whitespace-pre-line">{{ $submission->feedback }}</p>
                        </div>
                    @endif
                @endif
            </div>
        @endif

        <!-- Form Pengumpulan -->
        @if ($submission && $submission->isGraded())
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Jawaban Kamu</h2>
                @if ($submission->content)
                    <p class="text-sm text-gray-700 whitespace-pre-line">{{ $submission->content }}</p>
                @endif
                @if ($submission->attachment)
                    <a href="{{ asset('storage/' . $submission->attachment) }}"
                        class="inline-block mt-3 text-sm text-indigo-600 font-medium hover:underline">
                        📎 Unduh berkas yang dikumpulkan
                    </a>
                @endif
                <p class="text-xs text-gray-400 mt-4">Tugas sudah dinilai sehingga tidak dapat diubah lagi.</p>
            </div>
        @else
            <form action="{{ route('assignment.submit', [$course->slug, $assignment->id]) }}" method="POST"
                enctype="multipart/form-data" class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                @csrf

                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">
                    {{ $submission ? 'Perbarui Pengumpulan' : 'Kumpulkan Tugas' }}
                </h2>

                <div>
                    <label for="content" class="block text-sm font-medium text-gray-700 mb-1">Jawaban (teks)</label>
                    <textarea id="content" name="content" rows="8"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition"
                        placeholder="Tulis jawaban kamu di sini...">{{ old('content', $submission->content ?? '') }}</textarea>
                </div>

                <div>
                    <label for="attachment" class="block text-sm font-medium text-gray-700 mb-1">
                        Lampiran (PDF / Doc / ZIP, maks 10 MB)
                    </label>
                    <input type="file" id="attachment" name="attachment" accept=".pdf,.doc,.docx,.zip"
                        class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 file:font-medium hover:file:bg-indigo-100">
                    @if ($submission?->attachment)
                        <p class="text-xs text-gray-500 mt-2">
                            Berkas saat ini:
                            <a href="{{ asset('storage/' . $submission->attachment) }}" class="text-indigo-600 hover:underline">unduh</a>.
                            Unggah berkas baru untuk menggantinya.
                        </p>
                    @endif
                </div>

                <button type="submit"
                    class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg shadow transition">
                    {{ $submission ? 'Perbarui Pengumpulan' : 'Kumpulkan Tugas' }}
                </button>
            </form>
        @endif
    </main>

</body>

</html>
