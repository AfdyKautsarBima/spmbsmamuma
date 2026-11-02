@extends('layouts.app')
@section('title', $size->exists ? 'Ubah Ukuran' : 'Tambah Ukuran')

@section('content')
    <h1 class="mb-4 text-xl font-semibold">{{ $size->exists ? 'Ubah Ukuran' : 'Tambah Ukuran' }}</h1>

    <form method="POST" action="{{ $size->exists ? route('uniform-sizes.update', $size) : route('uniform-sizes.store') }}"
          class="max-w-md space-y-4 rounded-lg bg-white p-5 shadow-sm">
        @csrf
        @if ($size->exists) @method('PUT') @endif
        @php($input = 'w-full rounded border border-slate-300 px-3 py-2 text-sm')

        <div>
            <label for="name" class="mb-1 block text-sm font-medium">Nama ukuran</label>
            <input id="name" name="name" required maxlength="20" value="{{ old('name', $size->name) }}" placeholder="XL" class="{{ $input }}">
        </div>
        <div>
            <label for="sort_order" class="mb-1 block text-sm font-medium">Urutan tampil</label>
            <input id="sort_order" name="sort_order" type="number" min="0" max="9999" value="{{ old('sort_order', $size->sort_order) }}" class="{{ $input }}">
        </div>

        <div class="flex gap-2">
            <button class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Simpan</button>
            <a href="{{ route('uniform-sizes.index') }}" class="rounded border px-4 py-2 text-sm hover:bg-slate-50">Batal</a>
        </div>
    </form>
@endsection
