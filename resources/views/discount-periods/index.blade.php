@extends('layouts.app')
@section('title', 'Periode Diskon')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-semibold">Master Data</h1>
        @if ($year)<a href="{{ route('discount-periods.create') }}" class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Tambah Periode Diskon</a>@endif
    </div>
    @include('partials.master-tabs')

    <p class="mb-3 max-w-2xl text-sm text-slate-600">
        Nominal tagihan dan batas minimal seragam ditentukan per periode. Pengaturan dibekukan saat pembayaran pertama,
        jadi perubahan periode tidak memengaruhi murid yang sudah ditandai.
    </p>

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-slate-50 text-slate-500">
                <tr>
                    <th class="px-4 py-3">Tahun ajaran</th><th class="px-4 py-3">Nama</th><th class="px-4 py-3">Mulai</th>
                    <th class="px-4 py-3">Akhir</th><th class="px-4 py-3 text-right">Harus dibayar</th>
                    <th class="px-4 py-3 text-right">Batas seragam</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($periods as $period)
                    <tr class="{{ $period->is_active ? '' : 'text-slate-400' }}">
                        <td class="px-4 py-3">{{ $period->academicYear->name }}</td>
                        <td class="px-4 py-3 font-medium">{{ $period->name }}</td>
                        <td class="px-4 py-3">{{ $period->starts_on->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">{{ $period->ends_on->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-right">{{ $period->payable_amount !== null ? 'Rp '.number_format($period->payable_amount, 0, ',', '.') : 'Belum diatur' }}</td>
                        <td class="px-4 py-3 text-right">{{ $period->uniform_threshold_amount !== null ? 'Rp '.number_format($period->uniform_threshold_amount, 0, ',', '.') : 'Belum diatur' }}</td>
                        <td class="px-4 py-3">{{ $period->is_active ? 'Aktif' : 'Nonaktif'.($period->payable_amount === null || $period->uniform_threshold_amount === null ? ' (perlu diatur ulang)' : '') }}</td>
                        <td class="flex justify-end gap-2 px-4 py-3">
                            @if ($period->academicYear->is_active)
                                <a href="{{ route('discount-periods.edit', $period) }}" class="rounded border px-3 py-1 text-slate-800 hover:bg-slate-50">Ubah</a>
                                <form method="POST" action="{{ route('discount-periods.toggle-active', $period) }}">
                                    @csrf @method('PATCH')
                                    <button class="rounded border px-3 py-1 text-slate-800 hover:bg-slate-50">{{ $period->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-6 text-center text-slate-500">Belum ada periode diskon.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
