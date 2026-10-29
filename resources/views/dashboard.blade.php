@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
    <h1 class="mb-4 text-xl font-semibold">Halo, {{ auth()->user()->name }}</h1>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-lg bg-white p-4 shadow-sm">
            <h2 class="mb-2 text-sm font-medium text-slate-500">Tahun ajaran aktif</h2>
            @if ($year)
                <p class="text-2xl font-semibold">{{ $year->name }}</p>
                <dl class="mt-3 space-y-1 text-sm">
                    <div class="flex justify-between"><dt>Biaya daftar ulang</dt><dd>Rp {{ number_format($year->registration_fee, 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between"><dt>Batas seragam</dt><dd>{{ $year->uniform_threshold_percent }}% dari tagihan</dd></div>
                </dl>
            @else
                <p class="text-sm text-slate-500">Belum ada tahun ajaran aktif.</p>
            @endif
        </div>

        @if ($stats)
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <h2 class="mb-2 text-sm font-medium text-slate-500">Pendaftar</h2>
                <dl class="space-y-1 text-sm">
                    <div class="flex justify-between"><dt>Total pendaftar</dt><dd class="font-semibold">{{ $stats['total'] }}</dd></div>
                    <div class="flex justify-between"><dt>Pendaftar hari ini</dt><dd>{{ $stats['today'] }}</dd></div>
                    <div class="flex justify-between"><dt>Nomor terakhir dipakai</dt><dd>{{ $stats['last'] ?? '-' }}</dd></div>
                    <div class="flex justify-between"><dt>Berkas lengkap</dt><dd>{{ $stats['complete'] }}</dd></div>
                    <div class="flex justify-between"><dt>Berkas belum lengkap</dt><dd>{{ $stats['incomplete'] }}</dd></div>
                </dl>
                <div class="mt-3 flex gap-2">
                    <a href="{{ route('applicants.create') }}" class="rounded bg-slate-800 px-3 py-2 text-sm text-white hover:bg-slate-700">Input pendaftar</a>
                    <a href="{{ route('documents.index', ['status' => 'belum']) }}" class="rounded border px-3 py-2 text-sm hover:bg-slate-50">Berkas belum lengkap</a>
                </div>
            </div>
        @endif

        @if (auth()->user()->isAdmin())
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <h2 class="mb-2 text-sm font-medium text-slate-500">Akun aktif per peran</h2>
                <ul class="space-y-1 text-sm">
                    @foreach (\App\Enums\Role::cases() as $role)
                        <li class="flex justify-between"><span>{{ $role->label() }}</span><span>{{ $roleCounts[$role->value] ?? 0 }}</span></li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <p class="mt-6 text-sm text-slate-500">Menu daftar ulang dan seragam akan ditambahkan pada tahap berikutnya.</p>
@endsection
