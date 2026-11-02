@extends('layouts.app')
@section('title', 'Jenis Seragam')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-semibold">Master Data</h1>
        @if ($year)<a href="{{ route('uniform-types.create') }}" class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Tambah Jenis Seragam</a>@endif
    </div>
    @include('partials.master-tabs')

    <p class="mb-3 max-w-2xl text-sm text-slate-600">
        Semua murid yang berhak menerima semua jenis seragam yang aktif. Tandai <strong>perlu ukuran</strong> hanya untuk seragam yang ukurannya dicatat (misalnya olahraga).
        Murid berhak setelah total pembayarannya mencapai batas persen dari tagihan bersih ({{ $year?->uniform_threshold_percent ?? 0 }}% untuk TA {{ $year?->name ?? '-' }}, diatur di menu Tahun Ajaran).
    </p>

    @if ($year && $otherYears->isNotEmpty())
        <form method="POST" action="{{ route('uniform-types.copy') }}" class="mb-4 flex flex-wrap items-center gap-2 text-sm">
            @csrf
            <span>Salin jenis seragam dari</span>
            <select name="from_year" class="rounded border border-slate-300 bg-white px-3 py-2">
                @foreach ($otherYears as $other)<option value="{{ $other->id }}">TA {{ $other->name }}</option>@endforeach
            </select>
            <button class="rounded border px-3 py-2 hover:bg-white">Salin ke TA {{ $year->name }}</button>
        </form>
    @endif

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-slate-50 text-slate-500">
                <tr><th class="px-4 py-3">Tahun ajaran</th><th class="px-4 py-3">Jenis seragam</th><th class="px-4 py-3">Perlu ukuran</th><th class="px-4 py-3">Urutan</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($types as $type)
                    <tr class="{{ $type->is_active ? '' : 'text-slate-400' }}">
                        <td class="px-4 py-3">{{ $type->academicYear->name }}</td>
                        <td class="px-4 py-3 font-medium">{{ $type->name }}</td>
                        <td class="px-4 py-3">{{ $type->needs_size ? 'Ya' : 'Tidak' }}</td>
                        <td class="px-4 py-3">{{ $type->sort_order }}</td>
                        <td class="px-4 py-3">{{ $type->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                        <td class="flex justify-end gap-2 px-4 py-3">
                            @if ($type->academicYear->is_active)
                                <a href="{{ route('uniform-types.edit', $type) }}" class="rounded border px-3 py-1 text-slate-800 hover:bg-slate-50">Ubah</a>
                                <form method="POST" action="{{ route('uniform-types.toggle-active', $type) }}">
                                    @csrf @method('PATCH')
                                    <button class="rounded border px-3 py-1 text-slate-800 hover:bg-slate-50">{{ $type->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">Belum ada jenis seragam.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
