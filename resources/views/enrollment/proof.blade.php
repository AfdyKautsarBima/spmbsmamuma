<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Bukti Daftar Ulang {{ $applicant->registration_number }}</title>
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
        td, th { padding: 5px 6px; text-align: left; vertical-align: top; }
        table.data td { border-bottom: 1px solid #bbb; }
        table.data td:first-child { width: 190px; }
        table.pay th, table.pay td { border: 1px solid #888; }
        .r { text-align: right; }
        .ttd { display: flex; justify-content: space-between; margin-top: 36px; text-align: center; }
        .ttd div { width: 220px; }
        .ttd .garis { margin-top: 64px; border-top: 1px solid #000; padding-top: 3px; }
        .no-print { text-align: center; margin: 14px 0; }
        .no-print button { padding: 8px 18px; font-size: 14px; cursor: pointer; }
        @media print { .no-print { display: none; } .wrap { padding: 0; } }
    </style>
</head>
<body>
@php($rp = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.'))
<div class="wrap">
    <div class="no-print"><button onclick="window.print()">Cetak</button></div>

    <div class="kop">
        <h1>{{ config('sekolah.name') }}</h1>
        <p>{{ config('sekolah.address') }}</p>
    </div>

    <h2>Bukti Daftar Ulang TA {{ $applicant->academicYear->name }}</h2>

    <table class="data">
        <tr><td>Nomor pendaftaran</td><td>: {{ $applicant->registration_number }}</td></tr>
        <tr><td>Nama murid</td><td>: {{ $applicant->name }}</td></tr>
        <tr><td>Asal sekolah</td><td>: {{ $applicant->school->name }}</td></tr>
        <tr><td>Status</td><td>: {{ $summary['status']->label() }}</td></tr>
        <tr><td>Biaya daftar ulang</td><td>: {{ $summary['billing'] ? $rp($summary['billing']->fee_snapshot) : '-' }}</td></tr>
        @if ($summary['billing']?->hasDiscount())
            <tr><td>Diskon</td><td>: {{ $summary['billing']->discount_label }} {{ $summary['billing']->discount_percent }}%</td></tr>
        @endif
        <tr><td>Tagihan bersih</td><td>: {{ $summary['net'] !== null ? $rp($summary['net']) : '-' }}</td></tr>
        <tr><td>Total dibayar</td><td>: {{ $rp($summary['paid']) }}</td></tr>
        <tr><td>Sisa tagihan</td><td>: {{ $summary['remaining'] !== null ? $rp($summary['remaining']) : '-' }}</td></tr>
    </table>

    <p style="margin: 16px 0 6px"><strong>Rincian pembayaran</strong></p>
    <table class="pay">
        <thead><tr><th>Kwitansi</th><th>Tanggal</th><th class="r">Nominal</th><th>Penerima</th></tr></thead>
        <tbody>
            @forelse ($applicant->payments->whereNull('cancelled_at') as $payment)
                <tr><td>{{ $payment->receipt_number }}</td><td>{{ $payment->paid_at->format('d/m/Y') }}</td><td class="r">{{ $rp($payment->amount) }}</td><td>{{ $payment->receiver->name }}</td></tr>
            @empty
                <tr><td colspan="4">Belum ada pembayaran.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="ttd">
        <div>Orang tua/wali<div class="garis">(............................)</div></div>
        <div>Panitia<div class="garis">(............................)</div></div>
    </div>
</div>
</body>
</html>
