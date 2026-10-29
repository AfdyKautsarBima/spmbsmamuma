@extends('layouts.app')
@section('title', 'Wilayah')

@section('content')
    <h1 class="mb-4 text-xl font-semibold">Master Data</h1>
    @include('partials.master-tabs')

    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-lg bg-white p-4 shadow-sm">
            <h2 class="mb-2 text-sm font-medium text-slate-500">Data wilayah terimpor</h2>
            <ul class="space-y-1 text-sm">
                @foreach (\App\Models\Region::LEVELS as $level => $label)
                    <li class="flex justify-between"><span>{{ $label }}</span><span>{{ number_format($counts[$level] ?? 0, 0, ',', '.') }}</span></li>
                @endforeach
            </ul>

            <h2 class="mb-2 mt-4 text-sm font-medium text-slate-500">Pengaturan bawaan (config/spmb.php)</h2>
            <ul class="space-y-1 text-sm">
                @php($province = config('spmb.default_province_code'))
                <li>Provinsi bawaan: <code>{{ $province }}</code> {{ $names[$province] ?? '(kode tidak ditemukan)' }}</li>
                @foreach (config('spmb.priority_regency_codes', []) as $code)
                    <li>Diurutkan paling atas: <code>{{ $code }}</code> {{ $names[$code] ?? '(kode tidak ditemukan)' }}</li>
                @endforeach
            </ul>
        </div>

        <div class="rounded-lg bg-white p-4 shadow-sm text-sm">
            <h2 class="mb-2 font-medium">Cara mengimpor data</h2>
            <p class="mb-2 text-slate-600">Jalankan di terminal dari folder proyek:</p>
            <pre class="overflow-x-auto rounded bg-slate-100 p-3 text-xs">php artisan wilayah:import /path/ke/wilayah.sql</pre>
            <p class="mt-2 text-slate-600">Tambahkan <code>--replace</code> untuk mengosongkan data lama lebih dulu. Detail ada di panduan tahap 2.</p>
        </div>
    </div>

    <div class="mt-4 rounded-lg bg-white p-4 shadow-sm">
        <h2 class="mb-3 text-sm font-medium text-slate-500">Uji pemilih alamat</h2>
        @if (($counts[1] ?? 0) === 0)
            <p class="text-sm text-slate-500">Belum ada data. Impor dulu untuk mencoba.</p>
        @else
            <x-region-picker :levels="4" />
        @endif
    </div>
@endsection
