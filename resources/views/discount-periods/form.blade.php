@extends('layouts.app')
@section('title', $period->exists ? 'Ubah Periode Diskon' : 'Tambah Periode Diskon')

@section('content')
    <h1 class="mb-4 text-xl font-semibold">{{ $period->exists ? 'Ubah Periode Diskon' : 'Tambah Periode Diskon' }}</h1>

    <form method="POST" action="{{ $period->exists ? route('discount-periods.update', $period) : route('discount-periods.store') }}"
          class="max-w-md space-y-4 rounded-lg bg-white p-5 shadow-sm">
        @csrf
        @if ($period->exists) @method('PUT') @endif

        @php($input = 'w-full rounded border border-slate-300 px-3 py-2 text-sm')
        <div>
            <label for="name" class="mb-1 block text-sm font-medium">Nama periode</label>
            <input id="name" name="name" required value="{{ old('name', $period->name) }}" placeholder="Pendaftaran gelombang 1" class="{{ $input }}">
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="starts_on" class="mb-1 block text-sm font-medium">Tanggal mulai</label>
                <x-date-input id="starts_on" name="starts_on" :required="true" class="w-full" :value="old('starts_on', $period->starts_on?->format('d/m/Y'))" />
            </div>
            <div>
                <label for="ends_on" class="mb-1 block text-sm font-medium">Tanggal akhir</label>
                <x-date-input id="ends_on" name="ends_on" :required="true" class="w-full" :value="old('ends_on', $period->ends_on?->format('d/m/Y'))" />
            </div>
        </div>
        <div>
            <label for="percent" class="mb-1 block text-sm font-medium">Diskon (%)</label>
            <input id="percent" name="percent" type="number" min="1" max="99" required value="{{ old('percent', $period->percent) }}" class="{{ $input }}">
            <p class="mt-1 text-xs text-slate-500">1 sampai 99. Tagihan bersih dibulatkan ke bawah.</p>
        </div>
        <div>
            <label for="description" class="mb-1 block text-sm font-medium">Keterangan (opsional)</label>
            <input id="description" name="description" value="{{ old('description', $period->description) }}" class="{{ $input }}">
        </div>

        <div class="flex gap-2">
            <button class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Simpan</button>
            <a href="{{ route('discount-periods.index') }}" class="rounded border px-4 py-2 text-sm hover:bg-slate-50">Batal</a>
        </div>
    </form>
@endsection
