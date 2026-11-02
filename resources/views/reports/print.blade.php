<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title }}</title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #000; margin: 0; }
        .wrap { padding: 8px; }
        .kop { text-align: center; border-bottom: 2px solid #000; padding-bottom: 6px; margin-bottom: 10px; }
        .kop h1 { font-size: 16px; margin: 0; text-transform: uppercase; }
        .kop p { margin: 2px 0 0; font-size: 11px; }
        h2.title { text-align: center; font-size: 14px; margin: 0; text-transform: uppercase; }
        p.sub { text-align: center; margin: 2px 0 10px; }
        h3 { font-size: 12px; margin: 14px 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { border: 1px solid #777; padding: 3px 5px; text-align: left; vertical-align: top; }
        th { background: #e2e8f0; }
        .r { text-align: right; white-space: nowrap; }
        tfoot td { font-weight: bold; background: #f1f5f9; }
        .notes { margin-top: 8px; }
        .notes p { margin: 2px 0; }
        .meta { margin-top: 12px; font-size: 10px; color: #444; }
        .no-print { text-align: center; margin: 10px 0; }
        .no-print button { padding: 8px 18px; font-size: 14px; cursor: pointer; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        @media print { .no-print { display: none; } .wrap { padding: 0; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="no-print">
        <button onclick="window.print()">Cetak / Simpan sebagai PDF</button>
        <p>Di dialog cetak, pilih "Simpan sebagai PDF" sebagai printer.</p>
    </div>

    <div class="kop">
        <h1>{{ config('sekolah.name') }}</h1>
        <p>{{ config('sekolah.address') }}</p>
    </div>

    <h2 class="title">{{ $report->title }}</h2>
    <p class="sub">{{ $report->subtitle }}</p>

    @foreach ($report->sections as $section)
        @if ($section['title'])<h3>{{ $section['title'] }}</h3>@endif
        <table>
            <thead>
                <tr>@foreach ($section['columns'] as $column)<th class="{{ $column['type'] !== 'text' ? 'r' : '' }}">{{ $column['label'] }}</th>@endforeach</tr>
            </thead>
            <tbody>
                @forelse ($section['rows'] as $row)
                    <tr>
                        @foreach ($row as $i => $value)
                            @php($cell = \App\Reports\Report::cell($value, $section['columns'][$i]['type'] ?? 'text'))
                            <td class="{{ \App\Reports\Report::isNumeric($cell) ? 'r' : '' }}">{{ \App\Reports\Report::display($cell) }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($section['columns']) }}">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
            @if ($section['footer'])
                <tfoot>
                    <tr>
                        @foreach ($section['footer'] as $i => $value)
                            @php($cell = \App\Reports\Report::cell($value, $section['columns'][$i]['type'] ?? 'text'))
                            <td class="{{ \App\Reports\Report::isNumeric($cell) ? 'r' : '' }}">{{ \App\Reports\Report::display($cell) }}</td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
    @endforeach

    <div class="notes">
        @foreach ($report->notes as $note)<p>{{ $note }}</p>@endforeach
    </div>
    <p class="meta">Dicetak oleh {{ auth()->user()->name }} pada {{ now()->format('d/m/Y H:i') }}</p>
</div>
</body>
</html>
