<x-filament-panels::page>
    {{ $this->form }}

    @php
        $columns = $this->columns;
        $rows = $this->rows;
    @endphp

    @if (! $this->courseId)
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Belum ada kursus yang bisa ditampilkan.
            </p>
        </x-filament::section>
    @elseif ($columns->isEmpty())
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Kursus ini belum memiliki kuis atau tugas bernilai.
            </p>
        </x-filament::section>
    @elseif ($rows->isEmpty())
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Belum ada siswa terdaftar di kursus ini.
            </p>
        </x-filament::section>
    @else
        <x-filament::section>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-white/10">
                            <th class="text-left font-semibold px-3 py-2 whitespace-nowrap">Siswa</th>
                            @foreach ($columns as $column)
                                <th class="text-center font-semibold px-3 py-2 whitespace-nowrap">
                                    <span class="block">{{ $column['title'] }}</span>
                                    <span class="block text-xs font-normal text-gray-400">
                                        {{ $column['type'] === 'quiz' ? 'Kuis' : 'Tugas' }} · maks {{ $column['max'] }}
                                    </span>
                                </th>
                            @endforeach
                            <th class="text-right font-semibold px-3 py-2 whitespace-nowrap">Progres</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <span class="font-medium">{{ $row['student']->name }}</span>
                                    <span class="block text-xs text-gray-400">{{ $row['student']->email }}</span>
                                </td>

                                @foreach ($columns as $column)
                                    @php $cell = $row['cells'][$column['key']]; @endphp
                                    <td class="px-3 py-2 text-center whitespace-nowrap">
                                        @if ($cell['pending'])
                                            <x-filament::badge color="warning">Perlu dinilai</x-filament::badge>
                                        @elseif ($cell['score'] === null)
                                            <span class="text-gray-300 dark:text-gray-600">—</span>
                                        @else
                                            <x-filament::badge :color="$cell['passed'] ? 'success' : 'danger'">
                                                {{ $cell['score'] }}
                                            </x-filament::badge>
                                        @endif
                                    </td>
                                @endforeach

                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    <x-filament::badge :color="$row['status'] === 'completed' ? 'success' : 'gray'">
                                        {{ $row['progress'] }}%
                                    </x-filament::badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
