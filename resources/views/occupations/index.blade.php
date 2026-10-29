@extends('layouts.app')
@section('title', 'Pekerjaan')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-semibold">Master Data</h1>
        <a href="{{ route('occupations.create') }}" class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Tambah Pekerjaan</a>
    </div>
    @include('partials.master-tabs')

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-slate-50 text-slate-500">
                <tr>
                    <th class="px-4 py-3">Urutan</th>
                    <th class="px-4 py-3">Pekerjaan</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($occupations as $occupation)
                    <tr class="{{ $occupation->is_active ? '' : 'text-slate-400' }}">
                        <td class="px-4 py-2">{{ $occupation->sort_order }}</td>
                        <td class="px-4 py-2 font-medium">{{ $occupation->name }}</td>
                        <td class="px-4 py-2">{{ $occupation->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                        <td class="flex justify-end gap-2 px-4 py-2">
                            <a href="{{ route('occupations.edit', $occupation) }}" class="rounded border px-3 py-1 text-slate-800 hover:bg-slate-50">Ubah</a>
                            <form method="POST" action="{{ route('occupations.toggle-active', $occupation) }}">
                                @csrf @method('PATCH')
                                <button class="rounded border px-3 py-1 text-slate-800 hover:bg-slate-50">{{ $occupation->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
