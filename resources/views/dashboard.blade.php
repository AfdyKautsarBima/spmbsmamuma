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

    @if ($enrollment)
        @php($rp = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.'))
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <h2 class="mb-2 text-sm font-medium text-slate-500">Daftar ulang hari ini</h2>
                <dl class="space-y-1 text-sm">
                    <div class="flex justify-between"><dt>Uang masuk hari ini</dt><dd class="font-semibold">{{ $rp($enrollment['today_money']) }}</dd></div>
                    <div class="flex justify-between"><dt>Murid membayar hari ini</dt><dd>{{ $enrollment['today_students'] }}</dd></div>
                    <div class="flex justify-between"><dt>Sedang menyicil</dt><dd>{{ $enrollment['counts']['menyicil'] }}</dd></div>
                    <div class="flex justify-between"><dt>Sisa tagihan yang menyicil</dt><dd>{{ $rp($enrollment['money']['installment_remaining']) }}</dd></div>
                </dl>
                <a href="{{ route('enrollment.index') }}" class="mt-3 inline-block rounded bg-slate-800 px-3 py-2 text-sm text-white hover:bg-slate-700">Buka daftar ulang</a>
            </div>

            @if (auth()->user()->isAdmin())
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <h2 class="mb-2 text-sm font-medium text-slate-500">Status dan keuangan</h2>
                    <dl class="space-y-1 text-sm">
                        @foreach (\App\Enums\EnrollmentStatus::cases() as $s)
                            <div class="flex justify-between"><dt>{{ $s->label() }}</dt><dd>{{ $enrollment['counts'][$s->value] }}</dd></div>
                        @endforeach
                        <div class="mt-2 flex justify-between border-t pt-2"><dt>Tagihan kotor</dt><dd>{{ $rp($enrollment['money']['gross']) }}</dd></div>
                        <div class="flex justify-between"><dt>Total diskon</dt><dd>{{ $rp($enrollment['money']['discount']) }}</dd></div>
                        <div class="flex justify-between"><dt>Tagihan bersih</dt><dd>{{ $rp($enrollment['money']['net']) }}</dd></div>
                        <div class="flex justify-between"><dt>Total diterima</dt><dd class="font-semibold">{{ $rp($enrollment['money']['paid']) }}</dd></div>
                        <div class="flex justify-between"><dt>Total sisa</dt><dd>{{ $rp($enrollment['money']['remaining']) }}</dd></div>
                    </dl>
                    @if ($enrollment['overpaid'] > 0 || $enrollment['withdrawn_with_payment'] > 0)
                        <div class="mt-3 space-y-1 rounded border border-amber-300 bg-amber-50 p-2 text-xs text-amber-900">
                            @if ($enrollment['overpaid'] > 0)<p>{{ $enrollment['overpaid'] }} murid kelebihan bayar.</p>@endif
                            @if ($enrollment['withdrawn_with_payment'] > 0)<p>{{ $enrollment['withdrawn_with_payment'] }} murid mundur sudah membayar (total {{ $rp($enrollment['money']['withdrawn_paid']) }}).</p>@endif
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @endif

    <p class="mt-6 text-sm text-slate-500">Menu seragam akan ditambahkan pada tahap berikutnya.</p>
@endsection
