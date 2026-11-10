@extends('layouts.app')
@section('title', $year->exists ? 'Ubah Tahun Ajaran' : 'Tambah Tahun Ajaran')

@section('content')
    <h1 class="mb-4 text-xl font-semibold">{{ $year->exists ? 'Ubah Tahun Ajaran '.$year->name : 'Tambah Tahun Ajaran' }}</h1>

    @if ($copiedFrom)
        <p class="mb-4 max-w-md text-sm text-slate-500">Pengaturan di bawah disalin dari tahun {{ $copiedFrom }}. Silakan sesuaikan.</p>
    @endif

    <form method="POST" action="{{ $year->exists ? route('academic-years.update', $year) : route('academic-years.store') }}"
          class="max-w-md space-y-4 rounded-lg bg-white p-5 shadow-sm">
        @csrf
        @if ($year->exists) @method('PUT') @endif

        <div>
            <label for="name" class="mb-1 block text-sm font-medium">Tahun ajaran</label>
            <input id="name" name="name" value="{{ old('name', $year->name) }}" placeholder="2026/2027" required
                   class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="registration_fee" class="mb-1 block text-sm font-medium">Biaya daftar ulang (Rp)</label>
            <input id="registration_fee" name="registration_fee" type="number" min="0" step="1" required
                   value="{{ old('registration_fee', $year->registration_fee) }}"
                   class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="uniform_threshold_amount" class="mb-1 block text-sm font-medium">Batas minimal seragam (Rp)</label>
            <input id="uniform_threshold_amount" name="uniform_threshold_amount" type="number" min="0" max="1000000000" step="1" required
                   value="{{ old('uniform_threshold_amount', $year->uniform_threshold_amount) }}"
                   class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div class="flex gap-2">
            <button class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Simpan</button>
            <a href="{{ route('academic-years.index') }}" class="rounded border px-4 py-2 text-sm hover:bg-slate-50">Batal</a>
        </div>
    </form>
@endsection
