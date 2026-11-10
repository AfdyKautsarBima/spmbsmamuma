@extends('layouts.app')
@section('title', 'Daftar Ulang '.$applicant->registration_number)

@php
    $rp = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.');
    $status = $summary['status'];
    $isAdmin = auth()->user()->isAdmin();
    $withdrawn = $applicant->withdrawn_at !== null;
    $input = 'w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm';
@endphp

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-xl font-semibold">
            {{ $applicant->registration_number }} - {{ $applicant->name }}
            <span class="ml-2 rounded px-2 py-1 text-xs font-normal {{ $status->badgeClass() }}">{{ $status->label() }}</span>
        </h1>
        <div class="flex flex-wrap gap-2 text-sm">
            <a href="{{ route('enrollment.proof', $applicant) }}" target="_blank" class="rounded border bg-white px-3 py-2 hover:bg-slate-50">Bukti daftar ulang</a>
            <a href="{{ route('enrollment.index') }}" class="rounded border bg-white px-3 py-2 hover:bg-slate-50">Kembali ke daftar</a>
        </div>
    </div>

    @unless ($canWrite)
        <div class="mb-4 rounded border border-slate-300 bg-slate-50 p-3 text-sm text-slate-700">Tahun ajaran arsip. Data hanya bisa dilihat.</div>
    @endunless

    @if (session('new_receipt'))
        <div class="mb-4 rounded border border-green-300 bg-green-50 p-3 text-sm text-green-800">
            <a href="{{ route('enrollment.receipt', session('new_receipt')) }}" target="_blank" class="underline">Cetak kwitansi pembayaran ini</a>
        </div>
    @endif

    @if ($summary['overpaid'] > 0)
        <div class="mb-4 rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
            Kelebihan bayar {{ $rp($summary['overpaid']) }} (total dibayar melebihi tagihan bersih). Urus selisihnya di luar sistem.
        </div>
    @endif

    @if ($withdrawn && $summary['has_payments'])
        <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            Murid mengundurkan diri sejak {{ $applicant->withdrawn_at->format('d/m/Y') }} dan sudah membayar {{ $rp($summary['paid']) }}.
            Pengembalian dana diurus di luar sistem.
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-lg bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-medium text-slate-500">Murid</h2>
            <dl class="space-y-2 text-sm">
                <div><dt class="text-slate-500">Asal sekolah</dt><dd class="font-medium">{{ $applicant->school->name }}</dd></div>
                <div><dt class="text-slate-500">Pembawa</dt><dd class="font-medium">{{ $applicant->referrer_name ?? '-' }}{{ $applicant->referrer_class_name ? ' ('.$applicant->referrer_class_name.')' : '' }}</dd></div>
                <div>
                    <dt class="text-slate-500">Status berkas (informasi)</dt>
                    <dd class="font-medium {{ $missing ? 'text-amber-700' : 'text-green-700' }}">
                        {{ $missing ? 'Belum lengkap: '.implode(', ', $missing) : 'Lengkap' }}
                    </dd>
                </div>
            </dl>
        </div>

        <div class="rounded-lg bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-medium text-slate-500">Tagihan</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt>Biaya daftar ulang</dt><dd>{{ $rp($info['fee']) }}</dd></div>
                <div class="flex justify-between">
                    <dt>
                        Potongan tagihan
                        @if ($info['type'] === 'periode')
                            <span class="ml-1 rounded bg-blue-100 px-2 py-0.5 text-xs text-blue-800">{{ $info['label'] }} (nominal periode)</span>
                        @elseif ($info['percent'] > 0)
                            <span class="ml-1 rounded bg-blue-100 px-2 py-0.5 text-xs text-blue-800">{{ $info['label'] }} {{ $info['percent'] }}%</span>
                        @endif
                    </dt>
                    <dd>{{ $info['net'] < $info['fee'] ? '- '.$rp($info['fee'] - $info['net']) : '-' }}</dd>
                </div>
                <div class="flex justify-between border-t pt-2 font-medium"><dt>Tagihan bersih{{ $info['frozen'] ? '' : ' (perkiraan)' }}</dt><dd>{{ $rp($info['net']) }}</dd></div>
                <div class="flex justify-between"><dt>Total dibayar</dt><dd>{{ $rp($summary['paid']) }}</dd></div>
                <div class="flex justify-between font-medium"><dt>Sisa tagihan</dt><dd>{{ $withdrawn ? '-' : $rp($summary['remaining'] ?? $info['net']) }}</dd></div>
            </dl>
            @unless ($info['frozen'])
                <p class="mt-2 text-xs text-slate-500">Tagihan dibekukan saat pembayaran pertama. Diskon periode mengikuti tanggal pembayaran pertama.</p>
            @endunless
        </div>
    </div>

    <div class="mt-4 rounded-lg bg-white p-4 shadow-sm">
        <h2 class="mb-3 text-sm font-medium text-slate-500">Riwayat pembayaran</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b text-slate-500">
                    <tr>
                        <th class="py-2 pr-4">Kwitansi</th><th class="py-2 pr-4">Tanggal</th>
                        <th class="py-2 pr-4 text-right">Nominal</th><th class="py-2 pr-4">Penerima</th>
                        <th class="py-2 pr-4">Catatan</th><th class="py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y align-top">
                    @forelse ($payments as $payment)
                        <tr class="{{ $payment->isCancelled() ? 'text-slate-400' : '' }}">
                            <td class="py-2 pr-4 font-medium {{ $payment->isCancelled() ? 'line-through' : '' }}">{{ $payment->receipt_number }}</td>
                            <td class="whitespace-nowrap py-2 pr-4">{{ $payment->paid_at->format('d/m/Y H:i') }}</td>
                            <td class="whitespace-nowrap py-2 pr-4 text-right {{ $payment->isCancelled() ? 'line-through' : '' }}">{{ $rp($payment->amount) }}</td>
                            <td class="py-2 pr-4">{{ $payment->receiver->name }}</td>
                            <td class="py-2 pr-4">
                                {{ $payment->note }}
                                @if ($payment->isCancelled())
                                    <p class="text-xs text-red-600">Dibatalkan {{ $payment->canceller?->name }}, {{ $payment->cancelled_at->format('d/m/Y H:i') }}: {{ $payment->cancel_reason }}</p>
                                @endif
                            </td>
                            <td class="py-2 text-right">
                                <a href="{{ route('enrollment.receipt', $payment) }}" target="_blank" class="rounded border px-2 py-1 text-slate-700 hover:bg-slate-50">Kwitansi</a>
                                @if ($isAdmin && $canWrite && ! $payment->isCancelled())
                                    <form method="POST" action="{{ route('enrollment.payments.cancel', $payment) }}" class="mt-2 flex gap-1"
                                          onsubmit="return confirm('Batalkan pembayaran {{ $payment->receipt_number }}?')">
                                        @csrf
                                        <input name="reason" required minlength="3" placeholder="Alasan batal" class="w-36 rounded border border-slate-300 px-2 py-1 text-xs">
                                        <button class="rounded border border-red-300 px-2 py-1 text-xs text-red-700 hover:bg-red-50">Batalkan</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-4 text-center text-slate-500">Belum ada pembayaran.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($canWrite && ! $withdrawn)
        <div class="mt-4 rounded-lg bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-medium text-slate-500">{{ $summary['has_payments'] ? 'Tambah pembayaran' : 'Daftar ulang: pembayaran pertama' }}</h2>
            @php($remaining = $summary['remaining'] ?? $info['net'])
            @if ($remaining > 0)
                <form method="POST" action="{{ route('enrollment.payments.store', $applicant) }}" class="grid max-w-2xl gap-3 sm:grid-cols-[1fr_1fr_auto]">
                    @csrf
                    <div>
                        <label for="amount" class="mb-1 block text-sm font-medium">Nominal (Rp)</label>
                        <input id="amount" name="amount" inputmode="numeric" required autocomplete="off" value="{{ old('amount') }}" class="{{ $input }}">
                        <button type="button" class="mt-1 text-xs text-slate-600 underline"
                                onclick="document.getElementById('amount').value='{{ $remaining }}'">Isi sisa tagihan ({{ $rp($remaining) }})</button>
                    </div>
                    <div>
                        <label for="note" class="mb-1 block text-sm font-medium">Catatan (opsional)</label>
                        <input id="note" name="note" maxlength="150" value="{{ old('note') }}" class="{{ $input }}">
                    </div>
                    <div class="sm:pt-6"><button class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Simpan</button></div>
                </form>
            @else
                <p class="text-sm text-slate-600">{{ $info['net'] > 0 ? 'Tagihan sudah lunas.' : 'Biaya daftar ulang belum diatur oleh admin.' }}</p>
            @endif
        </div>
    @endif

    @if ($canWrite)
        <div class="mt-4 rounded-lg bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-medium text-slate-500">Pengunduran diri</h2>
            @if ($withdrawn)
                <p class="text-sm">Mengundurkan diri sejak <strong>{{ $applicant->withdrawn_at->format('d/m/Y') }}</strong>. Alasan: {{ $applicant->withdrawn_reason }}</p>
                @if ($isAdmin)
                    <form method="POST" action="{{ route('enrollment.withdrawal.cancel', $applicant) }}" class="mt-3 flex flex-wrap gap-2">
                        @csrf
                        <input name="reason" required minlength="3" placeholder="Alasan membatalkan status mundur" class="w-72 rounded border border-slate-300 px-3 py-2 text-sm">
                        <button class="rounded border px-4 py-2 text-sm hover:bg-slate-50">Batalkan status mundur</button>
                    </form>
                @endif
            @elseif ($summary['has_payments'] && ! $isAdmin)
                <p class="text-sm text-slate-600">Murid ini sudah membayar. Hanya admin yang dapat menandainya mengundurkan diri.</p>
            @else
                <form method="POST" action="{{ route('enrollment.withdraw', $applicant) }}" class="grid max-w-2xl gap-3 sm:grid-cols-[160px_1fr_auto]"
                      onsubmit="return confirm('Tandai {{ $applicant->name }} mengundurkan diri?')">
                    @csrf
                    <x-date-input name="date" :required="true" :value="old('date', today()->format('d/m/Y'))" class="w-full" />
                    <input name="reason" required minlength="3" placeholder="Alasan" value="{{ old('reason') }}" class="{{ $input }}">
                    <button class="rounded border border-red-300 px-4 py-2 text-sm text-red-700 hover:bg-red-50">Tandai mundur</button>
                </form>
            @endif
        </div>
    @endif

    @if ($isAdmin && $canWrite)
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            @unless ($withdrawn)
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <h2 class="mb-1 text-sm font-medium text-slate-500">Diskon khusus (admin)</h2>
                    <p class="mb-3 text-xs text-slate-500">Menggantikan diskon periode. Isi 0 untuk meniadakan diskon. Maksimal 99%.</p>
                    <form method="POST" action="{{ route('enrollment.discount', $applicant) }}" class="space-y-2">
                        @csrf
                        <input type="number" name="percent" min="0" max="99" required placeholder="Persen" value="{{ old('percent', $info['percent']) }}" class="{{ $input }}">
                        <input name="reason" required minlength="3" placeholder="Alasan" class="{{ $input }}">
                        <button class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Simpan diskon</button>
                    </form>
                </div>
            @endunless
            @if ($withdrawn || $summary['has_payments'])
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <h2 class="mb-1 text-sm font-medium text-slate-500">Catatan pengembalian dana (admin)</h2>
                    <p class="mb-3 text-xs text-slate-500">Hanya catatan. Tidak memengaruhi angka mana pun.</p>
                    <form method="POST" action="{{ route('enrollment.refund-note', $applicant) }}" class="space-y-2">
                        @csrf @method('PUT')
                        <textarea name="refund_note" rows="3" maxlength="1000" class="{{ $input }}">{{ old('refund_note', $applicant->refund_note) }}</textarea>
                        <button class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Simpan catatan</button>
                    </form>
                </div>
            @endif
        </div>
    @endif
@endsection
