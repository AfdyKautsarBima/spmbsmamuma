@extends('layouts.app')
@section('title', $occupation->exists ? 'Ubah Pekerjaan' : 'Tambah Pekerjaan')

@section('content')
    <h1 class="mb-4 text-xl font-semibold">{{ $occupation->exists ? 'Ubah Pekerjaan' : 'Tambah Pekerjaan' }}</h1>

    <form method="POST" action="{{ $occupation->exists ? route('occupations.update', $occupation) : route('occupations.store') }}"
          class="max-w-md space-y-4 rounded-lg bg-white p-5 shadow-sm">
        @csrf
        @if ($occupation->exists) @method('PUT') @endif

        <div>
            <label for="name" class="mb-1 block text-sm font-medium">Nama pekerjaan</label>
            <input id="name" name="name" value="{{ old('name', $occupation->name) }}" required
                   class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="sort_order" class="mb-1 block text-sm font-medium">Urutan di dropdown</label>
            <input id="sort_order" name="sort_order" type="number" min="0" max="9999"
                   value="{{ old('sort_order', $occupation->sort_order) }}"
                   class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-slate-500">Angka kecil tampil lebih atas. "Lainnya" memakai 9000 agar selalu di bawah.</p>
        </div>

        <div class="flex gap-2">
            <button class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Simpan</button>
            <a href="{{ route('occupations.index') }}" class="rounded border px-4 py-2 text-sm hover:bg-slate-50">Batal</a>
        </div>
    </form>
@endsection
