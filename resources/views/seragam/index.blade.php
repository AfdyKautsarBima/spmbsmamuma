@extends('layouts.app')
@section('title', 'Seragam')

@section('content')
    <h1 class="mb-4 text-xl font-semibold">Seragam {{ $year ? 'TA '.$year->name : '' }}</h1>

    @if ($year && $typeCount === 0)
        <div class="mb-4 rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
            Belum ada jenis seragam aktif untuk tahun ajaran ini. Minta admin menambahkannya di Master Data &gt; Jenis Seragam.
        </div>
    @endif

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Nama atau nomor pendaftaran"
               class="w-64 rounded border border-slate-300 px-3 py-2 text-sm">
        <select name="status" class="rounded border border-slate-300 px-3 py-2 text-sm">
            @foreach ([
                'berhak' => 'Semua yang berhak', 'belum_ambil' => 'Berhak, belum ambil', 'sebagian' => 'Sebagian sudah diambil',
                'lengkap' => 'Sudah lengkap', 'belum_berhak' => 'Belum berhak', 'semua' => 'Semua murid',
                'mundur_ambil' => 'Mundur, seragam sudah diambil',
            ] as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="rounded border px-4 py-2 text-sm hover:bg-white">Filter</button>
    </form>

    <p class="mb-2 text-sm text-slate-500">{{ $applicants->total() }} murid</p>

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-slate-50 text-slate-500">
                <tr>
                    <th class="px-4 py-3">Nomor</th><th class="px-4 py-3">Nama</th><th class="px-4 py-3">Asal sekolah</th>
                    <th class="px-4 py-3 text-right">Dibayar / tagihan</th><th class="px-4 py-3">Hak seragam</th>
                    <th class="px-4 py-3">Diambil</th><th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($applicants as $a)
                    @php($s = $states[$a->id])
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 font-medium">{{ $a->registration_number }}</td>
                        <td class="px-4 py-3">{{ $a->name }}</td>
                        <td class="px-4 py-3">{{ $a->school->name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            Rp {{ number_format($s['paid'], 0, ',', '.') }}{{ $s['net'] !== null ? ' / Rp '.number_format($s['net'], 0, ',', '.') : '' }}
                        </td>
                        <td class="px-4 py-3">
                            @switch($s['status'])
                                @case('berhak')<span class="rounded bg-green-100 px-2 py-1 text-xs text-green-800">Berhak</span>@break
                                @case('mundur')<span class="rounded bg-red-100 px-2 py-1 text-xs text-red-700">Mundur</span>@break
                                @case('belum_bayar')<span class="text-xs text-slate-500">Belum ada pembayaran</span>@break
                                @default<span class="text-xs text-amber-700">Belum berhak, kurang Rp {{ number_format($s['shortage'], 0, ',', '.') }}</span>
                            @endswitch
                        </td>
                        <td class="px-4 py-3">{{ $a->handed_active }}/{{ $typeCount }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('seragam.show', $a) }}" class="rounded bg-slate-800 px-3 py-1 text-white hover:bg-slate-700">Buka</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $applicants->links() }}</div>
@endsection
