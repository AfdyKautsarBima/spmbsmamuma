@php
    $rpFmt = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.');
@endphp

<div class="mt-4 rounded-lg bg-white p-4 shadow-sm">
    <h2 class="mb-3 text-sm font-medium text-slate-500">
        Daftar ulang dan pembayaran
        <span class="ml-2 rounded px-2 py-1 text-xs {{ $enrollmentSummary['status']->badgeClass() }}">{{ $enrollmentSummary['status']->label() }}</span>
        @if ($enrollmentSummary['billing']?->hasDiscount())
            <span class="ml-1 rounded bg-blue-100 px-2 py-1 text-xs text-blue-800">{{ $enrollmentSummary['billing']->discount_type === 'periode' ? 'Harga '.$enrollmentSummary['billing']->discount_label : 'Diskon '.$enrollmentSummary['billing']->discount_percent.'%' }}</span>
        @endif
    </h2>
    <dl class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-3">
        <div class="flex justify-between sm:block"><dt class="text-slate-500">Tagihan bersih</dt><dd class="font-medium">{{ $enrollmentSummary['net'] !== null ? $rpFmt($enrollmentSummary['net']) : '-' }}</dd></div>
        <div class="flex justify-between sm:block"><dt class="text-slate-500">Total dibayar</dt><dd class="font-medium">{{ $rpFmt($enrollmentSummary['paid']) }}</dd></div>
        <div class="flex justify-between sm:block"><dt class="text-slate-500">Sisa</dt><dd class="font-medium">{{ $enrollmentSummary['remaining'] !== null ? $rpFmt($enrollmentSummary['remaining']) : '-' }}</dd></div>
    </dl>
</div>
