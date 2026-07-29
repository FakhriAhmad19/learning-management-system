<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $quiz->title }} — {{ $course->title }}</title>
    @vite(['resources/css/app.css'])
</head>

<body class="bg-gray-100 text-gray-800 font-sans">

    <!-- Top Bar -->
    <nav class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-3xl mx-auto px-4 h-14 flex items-center justify-between gap-3">
            <a href="{{ route('learn.show', $course->slug) }}" class="text-sm text-indigo-600 font-semibold hover:text-indigo-800">
                ← Kembali ke Ruang Belajar
            </a>
            <div class="flex items-center gap-3 text-xs text-gray-500">
                <span>Batas lulus: {{ $quiz->passing_score }}%</span>
                @if ($remainingAttempts !== null)
                    <span class="px-2 py-0.5 rounded-full {{ $remainingAttempts > 0 ? 'bg-gray-100' : 'bg-red-100 text-red-700' }}">
                        Sisa percobaan: {{ $remainingAttempts }}
                    </span>
                @endif
                @if ($deadline)
                    <span id="quiz-timer"
                        data-deadline="{{ $deadline->toIso8601String() }}"
                        class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-semibold tabular-nums">
                        --:--
                    </span>
                @endif
            </div>
        </div>
    </nav>

    <main class="max-w-3xl mx-auto px-4 py-8 space-y-6">

        <div>
            <p class="text-xs text-indigo-600 font-semibold uppercase tracking-wider">{{ $quiz->module->title }}</p>
            <h1 class="text-2xl font-extrabold text-gray-900">{{ $quiz->title }}</h1>
            @if ($quiz->hasTimeLimit())
                <p class="text-sm text-gray-500 mt-1">
                    Waktu pengerjaan: {{ $quiz->time_limit_minutes }} menit.
                    Jawaban terkirim otomatis saat waktu habis.
                </p>
                @if ($canAttempt)
                    <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mt-2">
                        Hitungan waktu sudah berjalan dan tetap berjalan meski halaman ditutup
                        atau dimuat ulang. Membuka kuis ini menggunakan satu kesempatan.
                    </p>
                @endif
            @endif
        </div>

        @if (session('success'))
            <div class="px-4 py-3 rounded-lg text-sm border bg-blue-50 text-blue-800 border-blue-200">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="px-4 py-3 rounded-lg text-sm border bg-red-50 text-red-800 border-red-200">
                {{ session('error') }}
            </div>
        @endif

        <!-- Hasil percobaan terakhir -->
        @if ($lastAttempt)
            @php $pending = $lastAttempt->needsReview(); @endphp
            <div class="bg-white rounded-xl border p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">
                            @if ($pending)
                                Nilai sementara (soal beropsi){{ '' }}
                            @else
                                Nilai terakhir kamu{{ $lastAttempt->expired ? ' (waktu habis)' : '' }}
                            @endif
                        </p>
                        <p class="text-3xl font-extrabold {{ $pending ? 'text-amber-600' : ($lastAttempt->passed ? 'text-green-600' : 'text-red-500') }}">
                            {{ $lastAttempt->score }}
                        </p>
                    </div>
                    <span class="px-4 py-1.5 rounded-full text-sm font-semibold
                        {{ $pending ? 'bg-amber-100 text-amber-700' : ($lastAttempt->passed ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700') }}">
                        {{ $pending ? 'MENUNGGU PENILAIAN' : ($lastAttempt->passed ? 'LULUS' : 'BELUM LULUS') }}
                    </span>
                </div>

                @if ($pending)
                    <p class="text-sm text-gray-500 border-t border-gray-100 pt-3">
                        Jawaban esai kamu sedang dinilai pengajar. Nilai akhir bisa berubah
                        setelah penilaian selesai.
                    </p>
                @endif

                {{-- Umpan balik pengajar per soal esai --}}
                @php $essayGrades = $lastAttempt->answerGrades->keyBy('question_id'); @endphp
                @foreach ($quiz->questions->where('type', \App\Models\Question::TYPE_ESSAY) as $essay)
                    @php $grade = $essayGrades->get($essay->id); @endphp
                    <div class="border-t border-gray-100 pt-3">
                        <p class="text-sm font-semibold text-gray-800">{{ $essay->question }}</p>
                        <p class="text-sm text-gray-600 mt-1 whitespace-pre-line">
                            {{ $lastAttempt->essayAnswerFor($essay) ?? '— tidak dijawab —' }}
                        </p>
                        @if ($grade)
                            <p class="text-sm mt-2">
                                <span class="font-semibold text-gray-700">Nilai:</span>
                                {{ $grade->score }} / {{ $essay->points }}
                            </p>
                            @if ($grade->feedback)
                                <p class="text-sm text-gray-600 mt-1 whitespace-pre-line">
                                    <span class="font-semibold text-gray-700">Catatan pengajar:</span>
                                    {{ $grade->feedback }}
                                </p>
                            @endif
                        @else
                            <p class="text-xs text-amber-700 mt-2">Menunggu penilaian</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <!-- Form Kuis -->
        @if ($quiz->questions->isEmpty())
            <div class="bg-white rounded-xl border p-6 text-center text-gray-500">Kuis ini belum memiliki pertanyaan.</div>
        @elseif (! $canAttempt)
            <div class="bg-white rounded-xl border p-6 text-center space-y-2">
                <p class="font-semibold text-gray-900">Kesempatan mengerjakan sudah habis</p>
                <p class="text-sm text-gray-500">
                    Kuis ini dibatasi {{ $quiz->max_attempts }} kali percobaan. Hubungi pengajar bila kamu perlu kesempatan tambahan.
                </p>
            </div>
        @else
            <form id="quiz-form" action="{{ route('quiz.submit', [$course->slug, $quiz->id]) }}" method="POST" class="space-y-5">
                @csrf
                @foreach ($quiz->questions as $index => $question)
                    <div class="bg-white rounded-xl border border-gray-200 p-5">
                        <div class="flex items-start justify-between gap-3 mb-4">
                            <p class="font-semibold text-gray-900">{{ $index + 1 }}. {{ $question->question }}</p>
                            <span class="shrink-0 flex items-center gap-2">
                                @if ($question->isTrueFalse())
                                    <span class="text-xs font-medium px-2 py-0.5 rounded bg-gray-100 text-gray-500">
                                        Benar / Salah
                                    </span>
                                @elseif ($question->isEssay())
                                    <span class="text-xs font-medium px-2 py-0.5 rounded bg-purple-100 text-purple-700">
                                        Esai
                                    </span>
                                @endif
                                @if ($question->points > 1)
                                    <span class="text-xs font-medium px-2 py-0.5 rounded bg-gray-100 text-gray-500">
                                        {{ $question->points }} poin
                                    </span>
                                @endif
                            </span>
                        </div>

                        @if ($question->isEssay())
                            <textarea name="answers[{{ $question->id }}]" rows="6" required maxlength="10000"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition text-sm"
                                placeholder="Tulis jawaban kamu di sini..."></textarea>
                            <p class="text-xs text-gray-400 mt-2">Jawaban esai dinilai manual oleh pengajar.</p>
                        @else
                            <div class="space-y-2">
                                @foreach ($question->options as $option)
                                    <label class="flex items-center gap-3 px-3 py-2 rounded-lg border border-gray-200 hover:bg-gray-50 cursor-pointer">
                                        <input type="radio" name="answers[{{ $question->id }}]" value="{{ $option->id }}"
                                            class="text-indigo-600 focus:ring-indigo-500" required>
                                        <span class="text-sm text-gray-700">{{ $option->option_text }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach

                <button type="submit"
                    class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg shadow transition">
                    Kirim Jawaban
                </button>
            </form>

            @if ($deadline)
                <script>
                    // Hitung mundur di browser; server tetap memvalidasi ulang batas waktu
                    // saat pengiriman, jadi mengubah skrip ini tidak menambah waktu.
                    (function () {
                        const el = document.getElementById('quiz-timer');
                        const form = document.getElementById('quiz-form');
                        if (!el || !form) return;

                        const deadline = new Date(el.dataset.deadline).getTime();
                        let submitted = false;

                        function tick() {
                            const remaining = Math.max(0, Math.floor((deadline - Date.now()) / 1000));
                            const minutes = String(Math.floor(remaining / 60)).padStart(2, '0');
                            const seconds = String(remaining % 60).padStart(2, '0');
                            el.textContent = `${minutes}:${seconds}`;

                            if (remaining <= 30) {
                                el.classList.remove('bg-amber-100', 'text-amber-800');
                                el.classList.add('bg-red-100', 'text-red-700');
                            }

                            if (remaining === 0 && !submitted) {
                                submitted = true;
                                // Radio wajib diisi akan menghalangi submit biasa,
                                // jadi kirim langsung tanpa validasi bawaan browser.
                                form.noValidate = true;
                                form.submit();
                            }
                        }

                        tick();
                        setInterval(tick, 1000);
                    })();
                </script>
            @endif
        @endif
    </main>

</body>

</html>
