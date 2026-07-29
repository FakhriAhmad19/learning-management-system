<x-filament-panels::page>
    @php
        $totals = $this->totals;
        $summaries = $this->summaries;
    @endphp

    <!-- Kartu angka gabungan -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach ([
            ['label' => 'Kursus', 'value' => $totals['courses']],
            ['label' => 'Total Siswa', 'value' => $totals['students']],
            ['label' => 'Tingkat Penyelesaian', 'value' => $totals['completion_rate'] . '%'],
            ['label' => 'Menunggu Dinilai', 'value' => $totals['pending_grading']],
        ] as $card)
            <x-filament::section>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p>
                <p class="text-2xl font-bold mt-1">{{ $card['value'] }}</p>
            </x-filament::section>
        @endforeach
    </div>

    <x-filament::section heading="Ringkasan per Kursus">
        @if ($summaries->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada kursus untuk dilaporkan.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-white/10">
                            <th class="text-left font-semibold px-3 py-2 whitespace-nowrap">Kursus</th>
                            <th class="text-left font-semibold px-3 py-2 whitespace-nowrap">Kategori</th>
                            <th class="text-right font-semibold px-3 py-2 whitespace-nowrap">Siswa</th>
                            <th class="text-right font-semibold px-3 py-2 whitespace-nowrap">Selesai</th>
                            <th class="text-right font-semibold px-3 py-2 whitespace-nowrap">Rata-rata Progres</th>
                            <th class="text-right font-semibold px-3 py-2 whitespace-nowrap">Penyelesaian</th>
                            <th class="text-right font-semibold px-3 py-2 whitespace-nowrap">Menunggu Dinilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summaries as $row)
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="px-3 py-2">
                                    <span class="font-medium">{{ $row['course']->title }}</span>
                                    <span class="block text-xs text-gray-400">{{ $row['course']->instructor->name ?? '-' }}</span>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    {{ $row['course']->category->name ?? '—' }}
                                </td>
                                <td class="px-3 py-2 text-right">{{ $row['students'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $row['completed'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $row['average_progress'] }}%</td>
                                <td class="px-3 py-2 text-right">
                                    <x-filament::badge :color="$row['completion_rate'] >= 50 ? 'success' : 'gray'">
                                        {{ $row['completion_rate'] }}%
                                    </x-filament::badge>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    @if ($row['pending_grading'] > 0)
                                        <x-filament::badge color="warning">{{ $row['pending_grading'] }}</x-filament::badge>
                                    @else
                                        <span class="text-gray-300 dark:text-gray-600">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
