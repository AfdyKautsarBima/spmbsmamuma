@extends('layouts.app')
@section('title', $report->title)

@section('content')
    <div class="mb-4 flex flex-wrap items-start justify-between gap-2">
        <div>
            <h1 class="text-xl font-semibold">{{ $report->title }}</h1>
            <p class="text-sm text-slate-500">{{ $report->subtitle }}</p>
        </div>
        <div class="flex flex-wrap gap-2 text-sm">
            <a href="{{ request()->fullUrlWithQuery(['format' => 'xlsx']) }}" class="rounded bg-slate-800 px-3 py-2 text-white hover:bg-slate-700">Unduh Excel</a>
            <a href="{{ request()->fullUrlWithQuery(['format' => 'print']) }}" target="_blank" class="rounded border bg-white px-3 py-2 hover:bg-slate-50">Cetak / Simpan PDF</a>
            <a href="{{ route('reports.index') }}" class="rounded border bg-white px-3 py-2 hover:bg-slate-50">Semua laporan</a>
        </div>
    </div>

    @if ($years->isNotEmpty() || $filterDefs)
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
            @if (request('referrer_name'))<input type="hidden" name="referrer_name" value="{{ request('referrer_name') }}">@endif
            @if (request()->has('referrer_class_name'))<input type="hidden" name="referrer_class_name" value="{{ request('referrer_class_name') }}">@endif
            @if (request('kategori'))<input type="hidden" name="kategori" value="{{ request('kategori') }}">@endif

            @if ($years->isNotEmpty())
                <div>
                    <label class="mb-1 block text-xs text-slate-500">Tahun ajaran</label>
                    <select name="year" class="h-[38px] w-56 rounded border border-slate-300 bg-white px-3 text-sm">
                        @foreach ($years as $y)
                            <option value="{{ $y->id }}" @selected($y->id === $year->id)>TA {{ $y->name }}{{ $y->is_active ? ' (aktif)' : '' }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @foreach ($filterDefs as $def)
                <div>
                    <label class="mb-1 block text-xs text-slate-500">{{ $def['label'] }}</label>
                    @if ($def['type'] === 'date')
                        <x-date-input :name="$def['name']" :value="request($def['name'])" class="w-36" />
                    @elseif ($def['type'] === 'multi-select')
                        @php($selectedValues = (array) request($def['name'], isset($def['default']) ? [$def['default']] : []))
                        @php($selectedValues = array_map('strval', $selectedValues))
                        @php($selectedCount = count(array_intersect(array_map('strval', array_keys($def['options'])), $selectedValues)))
                        <details class="group relative {{ $def['name'] === 'status' ? 'w-[180px]' : 'w-56' }}"
                                 style="width: {{ $def['name'] === 'status' ? '180px' : '14rem' }};">
                            <summary class="flex h-[38px] cursor-pointer list-none items-center justify-between gap-1 rounded border border-slate-300 bg-white px-2 text-sm shadow-sm hover:border-slate-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500 [&::-webkit-details-marker]:hidden"
                                     style="height: 38px; display: flex;">
                                <span class="truncate">{{ $selectedCount ? $selectedCount.' dipilih' : 'Semua' }}</span>
                                <svg width="16" height="16" class="h-4 w-4 shrink-0 text-slate-500 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.22 7.47a.75.75 0 0 1 1.06 0L10 11.19l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                </svg>
                            </summary>
                            <div class="absolute left-0 z-20 mt-1 max-h-60 w-full overflow-y-auto rounded border border-slate-300 bg-white p-2 text-sm shadow-lg">
                                @foreach ($def['options'] as $value => $label)
                                    <label class="flex cursor-pointer items-start gap-2 rounded px-2 py-2 hover:bg-slate-50">
                                        <input type="checkbox" name="{{ $def['name'] }}[]" value="{{ $value }}"
                                               @checked(in_array((string) $value, $selectedValues, true))>
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </details>
                    @else
                        <select name="{{ $def['name'] }}" class="h-[38px] w-56 rounded border border-slate-300 bg-white px-3 text-sm">
                            @foreach ($def['options'] as $value => $label)
                                <option value="{{ $value }}" @selected((string) request($def['name']) === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    @endif
                    @if (isset($def['hint']))<p class="mt-1 max-w-xs text-xs text-slate-500">{{ $def['hint'] }}</p>@endif
                </div>
            @endforeach

            <button class="rounded border bg-white px-4 py-2 text-sm hover:bg-slate-50">Terapkan</button>
        </form>
    @endif

    @foreach ($report->sections as $section)
        <div class="mb-6">
            @if ($section['title'])<h2 class="mb-2 text-sm font-medium text-slate-600">{{ $section['title'] }}</h2>@endif
            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-slate-50 text-slate-500">
                        <tr>
                            @foreach ($section['columns'] as $column)
                                <th class="whitespace-nowrap px-3 py-2 {{ $column['type'] !== 'text' ? 'text-right' : '' }}">{{ $column['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse ($section['rows'] as $row)
                            <tr>
                                @foreach ($row as $i => $value)
                                    @php($cell = \App\Reports\Report::cell($value, $section['columns'][$i]['type'] ?? 'text'))
                                    <td class="px-3 py-2 {{ \App\Reports\Report::isNumeric($cell) ? 'whitespace-nowrap text-right' : '' }}">
                                        @if ($cell['url'])
                                            <a href="{{ $cell['url'] }}" class="text-blue-700 underline">{{ \App\Reports\Report::display($cell) }}</a>
                                        @else
                                            {{ \App\Reports\Report::display($cell) }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($section['columns']) }}" class="px-3 py-6 text-center text-slate-500">Tidak ada data.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($section['footer'])
                        <tfoot class="border-t bg-slate-50 font-medium">
                            <tr>
                                @foreach ($section['footer'] as $i => $value)
                                    @php($cell = \App\Reports\Report::cell($value, $section['columns'][$i]['type'] ?? 'text'))
                                    <td class="px-3 py-2 {{ \App\Reports\Report::isNumeric($cell) ? 'whitespace-nowrap text-right' : '' }}">{{ \App\Reports\Report::display($cell) }}</td>
                                @endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    @endforeach

    @foreach ($report->notes as $note)
        <p class="mb-1 text-sm text-slate-500">{{ $note }}</p>
    @endforeach
@endsection
