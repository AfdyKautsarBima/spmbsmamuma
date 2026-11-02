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
            @if (request('referrer'))<input type="hidden" name="referrer" value="{{ request('referrer') }}">@endif
            @if (request('kategori'))<input type="hidden" name="kategori" value="{{ request('kategori') }}">@endif

            @if ($years->isNotEmpty())
                <div>
                    <label class="mb-1 block text-xs text-slate-500">Tahun ajaran</label>
                    <select name="year" class="rounded border border-slate-300 bg-white px-3 py-2 text-sm">
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
                    @else
                        <select name="{{ $def['name'] }}" class="max-w-xs rounded border border-slate-300 bg-white px-3 py-2 text-sm">
                            @foreach ($def['options'] as $value => $label)
                                <option value="{{ $value }}" @selected((string) request($def['name']) === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    @endif
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
