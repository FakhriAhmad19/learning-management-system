<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $currentLesson->title }} — {{ $course->title }}</title>
    @vite(['resources/css/app.css'])
</head>

<body class="bg-gray-100 text-gray-800 font-sans">

    <!-- Top Bar -->
    <nav class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 h-14 flex items-center justify-between">
            <a href="{{ route('courses.show', $course->slug) }}" class="text-sm text-indigo-600 font-semibold hover:text-indigo-800">
                ← {{ Str::limit($course->title, 40) }}
            </a>
            <span class="text-xs text-gray-500 hidden sm:inline">Progress: {{ $enrollment->progress_percentage }}%</span>
        </div>
    </nav>

    <!-- Flash Message -->
    @if (session('success') || session('error'))
        <div class="max-w-7xl mx-auto px-4 pt-4">
            <div class="px-4 py-3 rounded-lg text-sm border {{ session('success') ? 'bg-green-50 text-green-800 border-green-200' : 'bg-red-50 text-red-800 border-red-200' }}">
                {{ session('success') ?? session('error') }}
            </div>
        </div>
    @endif

    <div class="max-w-7xl mx-auto px-4 py-8 grid grid-cols-1 lg:grid-cols-4 gap-8">

        <!-- Sticky Sidebar: Kurikulum -->
        <aside class="lg:col-span-1">
            <div class="lg:sticky lg:top-20 space-y-4">
                <!-- Progress Bar -->
                <div class="px-1">
                    <div class="flex items-center justify-between text-xs mb-1">
                        <span class="font-bold text-gray-500 uppercase tracking-wider">Progress</span>
                        <span class="font-semibold text-indigo-600">{{ $enrollment->progress_percentage }}%</span>
                    </div>
                    <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                        <div class="h-full bg-indigo-600 rounded-full transition-all" style="width: {{ $enrollment->progress_percentage }}%"></div>
                    </div>
                </div>

                @if ($enrollment->progress_percentage >= 100)
                    <a href="{{ route('certificate.show', $course->slug) }}"
                        class="flex items-center justify-center gap-2 px-3 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-lg shadow transition">
                        🎓 Lihat Sertifikat
                    </a>
                @endif

                <h2 class="text-xs font-bold text-gray-500 uppercase tracking-wider px-1">Daftar Materi</h2>
                <nav class="space-y-4">
                    @foreach ($course->modules as $module)
                        <div>
                            <p class="text-sm font-semibold text-gray-700 mb-2 px-1">{{ $module->title }}</p>
                            <ul class="space-y-1">
                                @foreach ($module->lessons as $lesson)
                                    @php
                                        $isActive = $lesson->id === $currentLesson->id;
                                        $isDone = in_array($lesson->id, $completedLessonIds);
                                    @endphp
                                    <li>
                                        <a href="{{ route('learn.show', [$course->slug, $lesson->slug]) }}"
                                            class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm transition
                                                {{ $isActive ? 'bg-indigo-600 text-white font-medium shadow' : 'text-gray-600 hover:bg-gray-200' }}">
                                            @if ($isDone)
                                                <svg class="w-4 h-4 flex-shrink-0 {{ $isActive ? 'text-white' : 'text-green-500' }}" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.5 7.5a1 1 0 01-1.42 0l-3.5-3.5a1 1 0 111.42-1.42l2.79 2.79 6.79-6.79a1 1 0 011.42 0z" clip-rule="evenodd" />
                                                </svg>
                                            @else
                                                <span class="w-4 h-4 flex items-center justify-center flex-shrink-0">
                                                    <span class="w-1.5 h-1.5 rounded-full {{ $isActive ? 'bg-white' : 'bg-gray-400' }}"></span>
                                                </span>
                                            @endif
                                            <span class="line-clamp-2">{{ $lesson->title }}</span>
                                        </a>
                                    </li>
                                @endforeach

                                {{-- Kuis akhir bab (bila ada) --}}
                                @if ($module->quiz)
                                    @php $quizPassed = in_array($module->quiz->id, $passedQuizIds); @endphp
                                    <li>
                                        <a href="{{ route('quiz.show', [$course->slug, $module->quiz->id]) }}"
                                            class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium
                                                {{ $quizPassed ? 'text-green-700 hover:bg-green-50' : 'text-amber-700 hover:bg-amber-50' }}">
                                            @if ($quizPassed)
                                                <svg class="w-4 h-4 flex-shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.5 7.5a1 1 0 01-1.42 0l-3.5-3.5a1 1 0 111.42-1.42l2.79 2.79 6.79-6.79a1 1 0 011.42 0z" clip-rule="evenodd" />
                                                </svg>
                                            @else
                                                <span class="w-4 h-4 flex items-center justify-center flex-shrink-0">📝</span>
                                            @endif
                                            <span class="line-clamp-2">{{ $module->quiz->title }}</span>
                                        </a>
                                    </li>
                                @endif

                                {{-- Tugas bab (bila ada) --}}
                                @foreach ($module->assignments as $assignment)
                                    @php $assignmentPassed = in_array($assignment->id, $passedAssignmentIds); @endphp
                                    <li>
                                        <a href="{{ route('assignment.show', [$course->slug, $assignment->id]) }}"
                                            class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium
                                                {{ $assignmentPassed ? 'text-green-700 hover:bg-green-50' : 'text-sky-700 hover:bg-sky-50' }}">
                                            @if ($assignmentPassed)
                                                <svg class="w-4 h-4 flex-shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.5 7.5a1 1 0 01-1.42 0l-3.5-3.5a1 1 0 111.42-1.42l2.79 2.79 6.79-6.79a1 1 0 011.42 0z" clip-rule="evenodd" />
                                                </svg>
                                            @else
                                                <span class="w-4 h-4 flex items-center justify-center flex-shrink-0">📄</span>
                                            @endif
                                            <span class="line-clamp-2">{{ $assignment->title }}</span>
                                            @if (in_array($assignment->id, $awaitingGradingAssignmentIds))
                                                <span class="ml-auto shrink-0 text-[10px] font-semibold px-1.5 py-0.5 rounded bg-amber-100 text-amber-700">
                                                    Menunggu
                                                </span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </nav>

                <a href="{{ route('leaderboard.show', $course->slug) }}"
                    class="mt-4 flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-amber-700 hover:bg-amber-50">
                    <span class="w-4 h-4 flex items-center justify-center flex-shrink-0">🏅</span>
                    <span>Papan Peringkat</span>
                </a>
            </div>
        </aside>

        <!-- Reading Area -->
        <main class="lg:col-span-3">
            <article class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sm:p-10">
                <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-6">{{ $currentLesson->title }}</h1>

                {{-- Isi Materi (RichText) --}}
                <div class="prose max-w-none text-gray-700 leading-relaxed space-y-4
                    [&_h2]:text-xl [&_h2]:font-bold [&_h2]:text-gray-900 [&_h2]:mt-6
                    [&_h3]:text-lg [&_h3]:font-semibold [&_h3]:text-gray-900 [&_h3]:mt-4
                    [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:list-decimal [&_ol]:pl-6
                    [&_code]:bg-gray-100 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:rounded [&_code]:text-sm [&_code]:text-pink-600
                    [&_a]:text-indigo-600 [&_a]:underline">
                    @if ($currentLesson->content)
                        {!! $currentLesson->content !!}
                    @else
                        <p class="text-gray-400 italic">Materi teks belum tersedia untuk pelajaran ini.</p>
                    @endif
                </div>

                {{-- Area Unduh Lampiran --}}
                @if ($currentLesson->attachment)
                    <div class="mt-8 p-4 bg-indigo-50 border border-indigo-100 rounded-lg flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-indigo-900">Berkas Lampiran</p>
                            <p class="text-xs text-indigo-600">Unduh bahan ajar pendukung materi ini.</p>
                        </div>
                        <a href="{{ asset('storage/' . $currentLesson->attachment) }}" download
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow">
                            Unduh Berkas
                        </a>
                    </div>
                @endif
            </article>

            <!-- Tandai Selesai (Progress Tracking) -->
            @php $currentDone = in_array($currentLesson->id, $completedLessonIds); @endphp
            <div class="mt-6 flex justify-center">
                @if ($currentDone)
                    <span class="inline-flex items-center gap-2 px-5 py-2.5 bg-green-50 text-green-700 font-semibold rounded-lg border border-green-200">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.5 7.5a1 1 0 01-1.42 0l-3.5-3.5a1 1 0 111.42-1.42l2.79 2.79 6.79-6.79a1 1 0 011.42 0z" clip-rule="evenodd" />
                        </svg>
                        Materi Selesai
                    </span>
                @else
                    <form action="{{ route('learn.complete', [$course->slug, $currentLesson->slug]) }}" method="POST">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg shadow transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Tandai Selesai
                        </button>
                    </form>
                @endif
            </div>

            <!-- Prev / Next Navigation -->
            <div class="flex items-center justify-between mt-6">
                @if ($prevLesson)
                    <a href="{{ route('learn.show', [$course->slug, $prevLesson->slug]) }}"
                        class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50">
                        ← Sebelumnya
                    </a>
                @else
                    <span></span>
                @endif

                @if ($nextLesson)
                    <a href="{{ route('learn.show', [$course->slug, $nextLesson->slug]) }}"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 shadow">
                        Selanjutnya →
                    </a>
                @endif
            </div>

            <!-- Diskusi / Tanya Jawab -->
            <section id="diskusi" class="mt-8 bg-white rounded-xl shadow-sm border border-gray-200 p-6 sm:p-8 space-y-6">
                <h2 class="text-lg font-bold text-gray-900">
                    Diskusi
                    <span class="text-sm font-normal text-gray-400">({{ $discussions->count() }} pertanyaan)</span>
                </h2>

                <!-- Form pertanyaan baru -->
                <form action="{{ route('discussions.store', [$course->slug, $currentLesson->slug]) }}" method="POST" class="space-y-3">
                    @csrf
                    <textarea name="body" rows="3" required
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition text-sm"
                        placeholder="Ada yang ingin ditanyakan tentang materi ini?">{{ old('parent_id') ? '' : old('body') }}</textarea>
                    <div class="flex justify-end">
                        <button type="submit"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow transition">
                            Kirim Pertanyaan
                        </button>
                    </div>
                </form>

                <!-- Daftar pertanyaan -->
                <div class="space-y-5 divide-y divide-gray-100">
                    @forelse ($discussions as $discussion)
                        <div class="pt-5 first:pt-0 space-y-3">
                            <!-- Pertanyaan -->
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900">
                                        {{ $discussion->author->name }}
                                        @if ($discussion->isFromInstructor())
                                            <span class="ml-1 text-xs font-medium px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700">Pengajar</span>
                                        @endif
                                        <span class="ml-1 text-xs font-normal text-gray-400">{{ $discussion->created_at->diffForHumans() }}</span>
                                    </p>
                                    <p class="text-sm text-gray-700 mt-1 whitespace-pre-line">{{ $discussion->body }}</p>
                                </div>

                                @can('delete', $discussion)
                                    <form action="{{ route('discussions.destroy', [$course->slug, $discussion->id]) }}" method="POST"
                                        onsubmit="return confirm('Hapus pertanyaan ini beserta balasannya?')">
                                        @csrf @method('DELETE')
                                        <button class="shrink-0 text-xs text-gray-400 hover:text-red-600">Hapus</button>
                                    </form>
                                @endcan
                            </div>

                            <!-- Balasan -->
                            @foreach ($discussion->replies as $reply)
                                <div class="ml-4 pl-4 border-l-2 {{ $reply->isFromInstructor() ? 'border-indigo-300 bg-indigo-50/40 rounded-r-lg py-2 pr-3' : 'border-gray-200' }}">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-gray-900">
                                                {{ $reply->author->name }}
                                                @if ($reply->isFromInstructor())
                                                    <span class="ml-1 text-xs font-medium px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700">Pengajar</span>
                                                @endif
                                                <span class="ml-1 text-xs font-normal text-gray-400">{{ $reply->created_at->diffForHumans() }}</span>
                                            </p>
                                            <p class="text-sm text-gray-700 mt-1 whitespace-pre-line">{{ $reply->body }}</p>
                                        </div>

                                        @can('delete', $reply)
                                            <form action="{{ route('discussions.destroy', [$course->slug, $reply->id]) }}" method="POST">
                                                @csrf @method('DELETE')
                                                <button class="shrink-0 text-xs text-gray-400 hover:text-red-600">Hapus</button>
                                            </form>
                                        @endcan
                                    </div>
                                </div>
                            @endforeach

                            <!-- Form balasan -->
                            <form action="{{ route('discussions.store', [$course->slug, $currentLesson->slug]) }}" method="POST"
                                class="ml-4 pl-4 flex items-start gap-2">
                                @csrf
                                <input type="hidden" name="parent_id" value="{{ $discussion->id }}">
                                <input type="text" name="body" required maxlength="5000" placeholder="Tulis balasan..."
                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition text-sm">
                                <button type="submit"
                                    class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition">
                                    Balas
                                </button>
                            </form>
                        </div>
                    @empty
                        <p class="pt-5 text-sm text-gray-500">Belum ada pertanyaan. Jadilah yang pertama bertanya!</p>
                    @endforelse
                </div>
            </section>
        </main>
    </div>

</body>

</html>
