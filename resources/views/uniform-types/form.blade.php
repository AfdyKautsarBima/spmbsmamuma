@extends('layouts.app')
@section('title', $type->exists ? 'Ubah Jenis Seragam' : 'Tambah Jenis Seragam')

@section('content')
    <h1 class="mb-4 text-xl font-semibold">{{ $type->exists ? 'Ubah Jenis Seragam' : 'Tambah Jenis Seragam' }}</h1>

    <form method="POST" action="{{ $type->exists ? route('uniform-types.update', $type) : route('uniform-types.store') }}"
          class="max-w-md space-y-4 rounded-lg bg-white p-5 shadow-sm">
        @csrf
        @if ($type->exists) @method('PUT') @endif
        @php($input = 'w-full rounded border border-slate-300 px-3 py-2 text-sm')

        <div>
            <label for="name" class="mb-1 block text-sm font-medium">Nama seragam</label>
            <input id="name" name="name" required value="{{ old('name', $type->name) }}" placeholder="Seragam olahraga" class="{{ $input }}">
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="needs_size" value="1" @checked(old('needs_size', $type->needs_size))> Perlu ukuran (S, M, L, ...)
        </label>
        <div>
            <label for="sort_order" class="mb-1 block text-sm font-medium">Urutan tampil</label>
            <input id="sort_order" name="sort_order" type="number" min="0" max="9999" value="{{ old('sort_order', $type->sort_order) }}" class="{{ $input }}">
        </div>

        <div class="flex gap-2">
            <button class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Simpan</button>
            <a href="{{ route('uniform-types.index') }}" class="rounded border px-4 py-2 text-sm hover:bg-slate-50">Batal</a>
        </div>
    </form>
@endsection
