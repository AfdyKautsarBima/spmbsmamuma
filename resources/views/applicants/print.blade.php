<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Bukti Pendaftaran {{ $applicant->registration_number }}</title>
    <style>
        @page { size: A4; margin: 18mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 13px; color: #000; margin: 0; }
        .wrap { max-width: 720px; margin: 0 auto; padding: 16px; }
        .kop { text-align: center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 14px; }
        .kop h1 { font-size: 18px; margin: 0; text-transform: uppercase; }
        .kop p { margin: 2px 0 0; font-size: 12px; }
        h2 { text-align: center; font-size: 15px; margin: 0 0 4px; text-transform: uppercase; }
        .tahun { text-align: center; margin-bottom: 12px; }
        .nomor { text-align: center; font-size: 28px; font-weight: bold; letter-spacing: 2px; border: 2px solid #000; padding: 8px; margin: 0 auto 14px; width: 220px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data td { padding: 5px 6px; vertical-align: top; border-bottom: 1px solid #bbb; }
        table.data td:first-child { width: 190px; }
        .ttd { display: flex; justify-content: space-between; margin-top: 36px; text-align: center; }
        .ttd div { width: 220px; }
        .ttd .garis { margin-top: 64px; border-top: 1px solid #000; padding-top: 3px; }
        .catatan { margin-top: 22px; font-size: 11px; }
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

    <h2>Bukti Pendaftaran</h2>
    <p class="tahun">Penerimaan Murid Baru Tahun Ajaran {{ $applicant->academicYear->name }}</p>
    <div class="nomor">{{ $applicant->registration_number }}</div>

    <table class="data">
        <tr><td>Nama murid</td><td>: {{ $applicant->name }}</td></tr>
        <tr><td>Jenis kelamin</td><td>: {{ $applicant->gender === 'L' ? 'Laki-laki' : 'Perempuan' }}</td></tr>
        <tr><td>Tempat, tanggal lahir</td><td>: {{ implode(', ', array_filter([$applicant->birth_place, $applicant->birth_date?->translatedFormat('d F Y')])) ?: '-' }}</td></tr>
        <tr><td>Nomor HP siswa</td><td>: {{ $applicant->student_phone ?? '-' }}</td></tr>
        <tr><td>Alamat</td><td>: {{ $applicant->fullAddress() }}</td></tr>
        <tr><td>Nama ayah</td><td>: {{ $applicant->father_name }}</td></tr>
        <tr><td>Nama ibu</td><td>: {{ $applicant->mother_name }}</td></tr>
        <tr><td>Nomor HP orang tua/wali</td><td>: {{ $applicant->phone ?? '-' }}</td></tr>
        <tr><td>Asal sekolah</td><td>: {{ $applicant->school->name }}, {{ $applicant->school->regency_name }}</td></tr>
        <tr><td>Tanggal pendaftaran</td><td>: {{ $applicant->created_at->translatedFormat('d F Y') }}</td></tr>
    </table>

    <div class="ttd">
        <div>Orang tua/wali<div class="garis">(............................)</div></div>
        <div>Panitia<div class="garis">({{ $applicant->creator->name }})</div></div>
    </div>

    <p class="catatan">Simpan bukti ini dan bawa saat daftar ulang.</p>
</div>
</body>
</html>
