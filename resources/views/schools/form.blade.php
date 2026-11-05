@extends('layouts.app')
@section('title', $school->exists ? 'Ubah Sekolah' : 'Tambah Sekolah')

@section('content')
    <h1 class="mb-4 text-xl font-semibold">{{ $school->exists ? 'Ubah Sekolah' : 'Tambah Sekolah' }}</h1>

    @if (\App\Models\Region::where('level', 2)->doesntExist())
        <div class="mb-4 max-w-xl rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
            Data wilayah belum diimpor, jadi kabupaten/kota belum bisa dipilih. Lihat tab Wilayah untuk petunjuk impor.
        </div>
    @endif

    <form method="POST" action="{{ $school->exists ? route('schools.update', $school) : route('schools.store') }}"
          class="max-w-xl space-y-4 rounded-lg bg-white p-5 shadow-sm">
        @csrf
        @if ($school->exists) @method('PUT') @endif

        <div>
            <label for="name" class="mb-1 block text-sm font-medium">Nama sekolah</label>
            <input id="name" name="name" value="{{ old('name', $school->name) }}" required
                   placeholder="SMP Negeri 1 Kudus" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="npsn" class="mb-1 block text-sm font-medium">NPSN (opsional)</label>
            <input id="npsn" name="npsn" value="{{ old('npsn', $school->npsn) }}" maxlength="8"
                   class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-slate-500">8 karakter huruf dan angka.</p>
        </div>
        <div>
            <p class="mb-2 text-sm font-medium">Kabupaten/kota asal</p>
            <x-region-picker :levels="2" :required="true"
                :values="$school->regency_code ? [substr($school->regency_code, 0, 2), $school->regency_code] : []" />
        </div>

        <div class="flex gap-2">
            <button class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Simpan</button>
            <a href="{{ route('schools.index') }}" class="rounded border px-4 py-2 text-sm hover:bg-slate-50">Batal</a>
        </div>
    </form>
@endsection
