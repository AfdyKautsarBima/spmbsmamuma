@extends('layouts.app')
@section('title', 'Tahun Ajaran')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-semibold">Tahun Ajaran</h1>
        <a href="{{ route('academic-years.create') }}" class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Tambah Tahun Ajaran</a>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-slate-50 text-slate-500">
                <tr>
                    <th class="px-4 py-3">Tahun ajaran</th>
                    <th class="px-4 py-3 text-right">Biaya daftar ulang</th>
                    <th class="px-4 py-3 text-right">Batas seragam</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($years as $year)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $year->name }}</td>
                        <td class="px-4 py-3 text-right">Rp {{ number_format($year->registration_fee, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right">Rp {{ number_format($year->uniform_threshold_amount, 0, ',', '.') }}</td>
                        <td class="px-4 py-3">
                            @if ($year->is_active)
                                <span class="rounded bg-green-100 px-2 py-1 text-xs text-green-800">Aktif</span>
                            @else
                                <span class="rounded bg-slate-100 px-2 py-1 text-xs text-slate-600">Arsip</span>
                            @endif
                        </td>
                        <td class="flex justify-end gap-2 px-4 py-3">
                            <a href="{{ route('academic-years.edit', $year) }}" class="rounded border px-3 py-1 hover:bg-slate-50">Ubah</a>
                            @unless ($year->is_active)
                                <form method="POST" action="{{ route('academic-years.activate', $year) }}"
                                      onsubmit="return confirm('Aktifkan tahun ajaran {{ $year->name }}? Tahun aktif saat ini akan menjadi arsip.')">
                                    @csrf @method('PATCH')
                                    <button class="rounded bg-slate-800 px-3 py-1 text-white hover:bg-slate-700">Aktifkan</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">Belum ada tahun ajaran.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
