@extends('layouts.app')
@section('title', 'Asal Sekolah')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-semibold">Master Data</h1>
        <a href="{{ route('schools.create') }}" class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Tambah Sekolah</a>
    </div>
    @include('partials.master-tabs')

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Cari nama, NPSN, atau kabupaten"
               class="rounded border border-slate-300 px-3 py-2 text-sm">
        <select name="status" class="rounded border border-slate-300 px-3 py-2 text-sm">
            <option value="">Semua status</option>
            <option value="aktif" @selected(request('status') === 'aktif')>Aktif</option>
            <option value="nonaktif" @selected(request('status') === 'nonaktif')>Nonaktif</option>
        </select>
        <button class="rounded border px-4 py-2 text-sm hover:bg-white">Cari</button>
    </form>

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-slate-50 text-slate-500">
                <tr>
                    <th class="px-4 py-3">Nama sekolah</th>
                    <th class="px-4 py-3">NPSN</th>
                    <th class="px-4 py-3">Kabupaten/kota asal</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($schools as $school)
                    <tr class="{{ $school->is_active ? '' : 'text-slate-400' }}">
                        <td class="px-4 py-3 font-medium">{{ $school->name }}</td>
                        <td class="px-4 py-3">{{ $school->npsn ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $school->regency_name }}</td>
                        <td class="px-4 py-3">{{ $school->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                        <td class="flex justify-end gap-2 px-4 py-3">
                            <a href="{{ route('schools.edit', $school) }}" class="rounded border px-3 py-1 text-slate-800 hover:bg-slate-50">Ubah</a>
                            <form method="POST" action="{{ route('schools.toggle-active', $school) }}">
                                @csrf @method('PATCH')
                                <button class="rounded border px-3 py-1 text-slate-800 hover:bg-slate-50">{{ $school->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">Belum ada sekolah.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $schools->links() }}</div>
@endsection
