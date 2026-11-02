<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Bukti Serah Terima Seragam {{ $applicant->registration_number }}</title>
    <style>
        @page { size: A4; margin: 18mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 13px; color: #000; margin: 0; }
        .wrap { max-width: 720px; margin: 0 auto; padding: 16px; }
        .kop { text-align: center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 14px; }
        .kop h1 { font-size: 18px; margin: 0; text-transform: uppercase; }
        .kop p { margin: 2px 0 0; font-size: 12px; }
        h2 { text-align: center; font-size: 15px; margin: 0 0 12px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 6px; text-align: left; vertical-align: top; }
        table.info td { border-bottom: 1px solid #bbb; }
        table.info td:first-child { width: 160px; }
        table.items th, table.items td { border: 1px solid #888; }
        .ttd { display: flex; justify-content: space-between; margin-top: 36px; text-align: center; }
        .ttd div { width: 220px; }
        .ttd .garis { margin-top: 64px; border-top: 1px solid #000; padding-top: 3px; }
        .no-print { text-align: center; margin: 14px 0; }
        .no-print button { padding: 8px 18px; font-size: 14px; cursor: pointer; }
        @media print { .no-print { display: none; } .wrap { padding: 0; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="no-print"><button onclick="window.print()">Cetak</button></div>

    <div class="kop">
        <h1>{{ config('sekolah.name') }}</h1>
        <p>{{ config('sekolah.address') }}</p>
    </div>

    <h2>Bukti Serah Terima Seragam TA {{ $applicant->academicYear->name }}</h2>

    <table class="info">
        <tr><td>Nomor pendaftaran</td><td>: {{ $applicant->registration_number }}</td></tr>
        <tr><td>Nama murid</td><td>: {{ $applicant->name }}</td></tr>
        <tr><td>Asal sekolah</td><td>: {{ $applicant->school->name }}</td></tr>
    </table>

    <table class="items" style="margin-top: 14px">
        <thead><tr><th>No</th><th>Jenis seragam</th><th>Ukuran</th><th>Tanggal diserahkan</th><th>Penyerah</th></tr></thead>
        <tbody>
            @forelse ($types as $type)
                @php($h = $handovers->get($type->id))
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $type->name }}</td>
                    <td>{{ $h?->size?->name ?? '-' }}</td>
                    <td>{{ $h ? $h->handed_at->format('d/m/Y') : 'Belum diambil' }}</td>
                    <td>{{ $h?->handler->name ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Belum ada jenis seragam.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="ttd">
        <div>Penerima (orang tua/wali)<div class="garis">(............................)</div></div>
        <div>Panitia seragam<div class="garis">(............................)</div></div>
    </div>
</div>
</body>
</html>
