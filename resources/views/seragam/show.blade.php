@extends('layouts.app')
@section('title', 'Seragam '.$applicant->registration_number)

@php
    $rp = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.');
    $isAdmin = auth()->user()->isAdmin();
    $entitled = $state['entitled'];
    $withdrawn = $applicant->withdrawn_at !== null;
    $input = 'rounded border border-slate-300 bg-white px-2 py-1 text-sm';
@endphp

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-xl font-semibold">
            {{ $applicant->registration_number }} - {{ $applicant->name }}
            @if ($entitled)<span class="ml-2 rounded bg-green-100 px-2 py-1 text-xs font-normal text-green-800">Berhak seragam</span>@endif
            @if ($withdrawn)<span class="ml-2 rounded bg-red-100 px-2 py-1 text-xs font-normal text-red-700">Mundur</span>@endif
        </h1>
        <div class="flex gap-2 text-sm">
            <a href="{{ route('seragam.proof', $applicant) }}" target="_blank" class="rounded border bg-white px-3 py-2 hover:bg-slate-50">Bukti serah terima</a>
            <a href="{{ route('seragam.index') }}" class="rounded border bg-white px-3 py-2 hover:bg-slate-50">Kembali ke daftar</a>
        </div>
    </div>

    @unless ($canWrite)
        <div class="mb-4 rounded border border-slate-300 bg-slate-50 p-3 text-sm text-slate-700">Tahun ajaran arsip. Data hanya bisa dilihat.</div>
    @endunless

    @foreach ($state['warnings'] as $warning)
        <div class="mb-3 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">{{ $warning }}</div>
    @endforeach

    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-lg bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-medium text-slate-500">Murid</h2>
            <dl class="space-y-2 text-sm">
                <div><dt class="text-slate-500">Asal sekolah</dt><dd class="font-medium">{{ $applicant->school->name }}</dd></div>
            </dl>
        </div>
        <div class="rounded-lg bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-medium text-slate-500">Pembayaran dan hak</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt>Tagihan bersih</dt><dd>{{ $state['net'] !== null ? $rp($state['net']) : '-' }}</dd></div>
                <div class="flex justify-between"><dt>Total dibayar</dt><dd>{{ $rp($state['paid']) }}</dd></div>
                <div class="flex justify-between"><dt>Ambang hak seragam</dt><dd>{{ $state['required'] !== null ? $rp($state['required']) : '-' }}</dd></div>
            </dl>
            <p class="mt-3 text-sm font-medium">
                @if ($entitled)
                    <span class="text-green-700">Berhak sejak {{ $state['entitlement']->granted_at->format('d/m/Y') }} (ambang saat itu {{ $rp($state['entitlement']->threshold_amount) }})</span>
                @elseif ($state['status'] === 'belum_bayar')
                    <span class="text-slate-500">Belum ada pembayaran.</span>
                @elseif ($state['status'] === 'belum_berhak')
                    <span class="text-amber-700">Belum berhak: kurang {{ $rp($state['shortage']) }} dari ambang.</span>
                @endif
            </p>
        </div>
    </div>

    <div class="mt-4 rounded-lg bg-white p-4 shadow-sm">
        <h2 class="mb-3 text-sm font-medium text-slate-500">Jenis seragam</h2>
        @forelse ($types as $type)
            @php
                $handover = $handovers->get($type->id);
                $selected = $chosen->get($type->id)?->uniform_size_id;
                $canAct = $canWrite && $entitled && ! $withdrawn;
            @endphp
            <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 py-3 last:border-0">
                <div class="w-48">
                    <p class="font-medium">{{ $type->name }}@unless ($type->is_active) <span class="text-xs text-slate-400">(nonaktif)</span>@endunless</p>
                    @if ($handover)
                        <p class="text-xs text-green-700">Diserahkan {{ $handover->handed_at->format('d/m/Y H:i') }} oleh {{ $handover->handler->name }}</p>
                    @else
                        <p class="text-xs text-slate-500">Belum diambil</p>
                    @endif
                </div>

                @if ($type->needs_size)
                    <form method="POST" action="{{ route('seragam.size', [$applicant, $type]) }}" class="flex items-center gap-2">
                        @csrf
                        <select name="uniform_size_id" required class="{{ $input }}" @disabled(! $canAct || ($handover && ! $isAdmin))>
                            <option value="">Ukuran</option>
                            @foreach ($sizes as $size)
                                @if ($size->is_active || $size->id === $selected)
                                    <option value="{{ $size->id }}" @selected($selected === $size->id)>{{ $size->name }}</option>
                                @endif
                            @endforeach
                        </select>
                        @if ($canAct && (! $handover || $isAdmin))
                            <button class="rounded border px-3 py-1 text-sm hover:bg-slate-50">Simpan ukuran</button>
                        @endif
                    </form>
                @endif

                <div class="ml-auto flex flex-wrap items-center gap-2">
                    @if (! $handover && $type->is_active)
                        <form method="POST" action="{{ route('seragam.handover', [$applicant, $type]) }}">
                            @csrf
                            <button class="rounded bg-slate-800 px-3 py-1 text-sm text-white hover:bg-slate-700 disabled:opacity-40"
                                    @disabled(! $canAct || ($type->needs_size && ! $selected))>Serahkan</button>
                        </form>
                    @endif
                    @if ($handover && $canWrite && ($isAdmin || $handover->handed_at->isToday()))
                        <form method="POST" action="{{ route('seragam.cancel', [$applicant, $type]) }}" class="flex gap-1"
                              onsubmit="return confirm('Batalkan penyerahan {{ $type->name }}?')">
                            @csrf
                            <input name="reason" maxlength="255" placeholder="{{ $handover->handed_at->isToday() ? 'Alasan (opsional)' : 'Alasan (wajib)' }}" class="w-40 {{ $input }}">
                            <button class="rounded border border-red-300 px-3 py-1 text-sm text-red-700 hover:bg-red-50">Batalkan</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-500">Belum ada jenis seragam aktif.</p>
        @endforelse
        @if ($entitled && $types->contains('needs_size', true))
            <p class="mt-2 text-xs text-slate-500">Seragam yang memakai ukuran baru bisa diserahkan setelah ukurannya dicatat.</p>
        @endif
    </div>
@endsection
