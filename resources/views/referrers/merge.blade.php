@extends('layouts.app')
@section('title', 'Gabungkan Pembawa')

@section('content')
    <h1 class="mb-4 text-xl font-semibold">Gabungkan Pembawa</h1>

    <div class="max-w-xl space-y-4 rounded-lg bg-white p-5 shadow-sm">
        <p class="text-sm">
            Pembawa <strong>{{ $referrer->name }}</strong> memiliki <strong>{{ $count }}</strong> pendaftar (semua tahun ajaran).
            Semua pendaftar itu akan dipindah ke pembawa tujuan, lalu nama <strong>{{ $referrer->name }}</strong> dihapus dari daftar.
            Langkah ini tidak bisa dibatalkan, tetapi setiap perpindahan tercatat di riwayat pendaftar.
        </p>

        <form method="POST" action="{{ route('referrers.merge', $referrer) }}" class="space-y-4"
              onsubmit="return confirm('Gabungkan {{ $referrer->name }} ke pembawa tujuan?')">
            @csrf
            <div>
                <label for="target_id" class="mb-1 block text-sm font-medium">Gabungkan ke</label>
                <select id="target_id" name="target_id" required class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="">-- Pilih pembawa tujuan --</option>
                    @foreach ($targets as $target)
                        <option value="{{ $target->id }}">{{ $target->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Gabungkan</button>
                <a href="{{ route('referrers.index') }}" class="rounded border px-4 py-2 text-sm hover:bg-slate-50">Batal</a>
            </div>
        </form>
    </div>
@endsection
