<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kwitansi {{ $payment->receipt_number }}</title>
    <style>
        @page { size: A5 landscape; margin: 12mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 13px; color: #000; margin: 0; }
        .wrap { max-width: 640px; margin: 0 auto; padding: 14px; position: relative; }
        .kop { text-align: center; border-bottom: 2px solid #000; padding-bottom: 6px; margin-bottom: 10px; }
        .kop h1 { font-size: 17px; margin: 0; text-transform: uppercase; }
        .kop p { margin: 2px 0 0; font-size: 11px; }
        h2 { text-align: center; margin: 0 0 2px; font-size: 16px; letter-spacing: 3px; }
        .no { text-align: center; margin-bottom: 10px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 4px 6px; vertical-align: top; }
        td:first-child { width: 150px; }
        .jumlah { margin: 12px 0; border: 2px solid #000; padding: 8px 12px; font-size: 18px; font-weight: bold; display: inline-block; }
        .ttd { display: flex; justify-content: flex-end; margin-top: 10px; text-align: center; }
        .ttd div { width: 210px; }
        .ttd .garis { margin-top: 48px; border-top: 1px solid #000; padding-top: 3px; }
        .batal { position: absolute; top: 40%; left: 0; right: 0; text-align: center; font-size: 56px; font-weight: bold; color: rgba(200,0,0,.35); transform: rotate(-12deg); }
        .no-print { text-align: center; margin: 12px 0; }
        .no-print button { padding: 8px 18px; font-size: 14px; cursor: pointer; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
@php($rp = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.'))
<div class="wrap">
    <div class="no-print"><button onclick="window.print()">Cetak</button></div>

    @if ($payment->isCancelled())<div class="batal">DIBATALKAN</div>@endif

    <div class="kop">
        <h1>{{ config('sekolah.name') }}</h1>
        <p>{{ config('sekolah.address') }}</p>
    </div>

    <h2>KWITANSI</h2>
    <p class="no">No. {{ $payment->receipt_number }}</p>

    <table>
        <tr><td>Telah terima dari</td><td>: {{ $applicant->father_name }} / {{ $applicant->mother_name }} (orang tua/wali)</td></tr>
        <tr><td>Untuk murid</td><td>: {{ $applicant->name }} ({{ $applicant->registration_number }}), asal {{ $applicant->school->name }}</td></tr>
        <tr><td>Terbilang</td><td>: <em>{{ ucfirst(\App\Services\Terbilang::rupiah($payment->amount)) }}</em></td></tr>
        <tr><td>Untuk pembayaran</td><td>: Biaya daftar ulang TA {{ $applicant->academicYear->name }}{{ $payment->note ? ' ('.$payment->note.')' : '' }}</td></tr>
        @if ($applicant->billing?->hasDiscount())
            <tr><td>Diskon</td><td>: {{ $applicant->billing->discount_label }} {{ $applicant->billing->discount_percent }}% (tagihan bersih {{ $rp($applicant->billing->net_bill) }})</td></tr>
        @endif
    </table>

    <div class="jumlah">{{ $rp($payment->amount) }}</div>

    <div class="ttd">
        <div>
            {{ $payment->paid_at->translatedFormat('d F Y') }}<br>Penerima,
            <div class="garis">{{ $payment->receiver->name }}</div>
        </div>
    </div>
</div>
</body>
</html>
