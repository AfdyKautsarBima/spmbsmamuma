@extends('layouts.app')
@section('title', 'Daftar Ulang')

@section('content')
    <h1 class="mb-4 text-xl font-semibold">Daftar Ulang {{ $year ? 'TA '.$year->name : '' }}</h1>

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Nama atau nomor pendaftaran"
               class="w-64 rounded border border-slate-300 px-3 py-2 text-sm">
        <select name="status" class="rounded border border-slate-300 px-3 py-2 text-sm">
            <option value="">Semua status</option>
            @foreach (\App\Enums\EnrollmentStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
        <button class="rounded border px-4 py-2 text-sm hover:bg-white">Filter</button>
    </form>

    <p class="mb-2 text-sm text-slate-500">{{ $applicants->total() }} pendaftar</p>

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-slate-50 text-slate-500">
                <tr>
                    <th class="px-4 py-3">Nomor</th>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Asal sekolah</th>
                    <th class="px-4 py-3">Berkas</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Dibayar</th>
                    <th class="px-4 py-3 text-right">Sisa</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($applicants as $a)
                    @php($s = $summaries[$a->id])
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 font-medium">{{ $a->registration_number }}</td>
                        <td class="px-4 py-3">{{ $a->name }}</td>
                        <td class="px-4 py-3">{{ $a->school->name }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs {{ $a->documents_complete ? 'text-green-700' : 'text-amber-700' }}">{{ $a->documents_complete ? 'Lengkap' : 'Belum lengkap' }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded px-2 py-1 text-xs {{ $s['status']->badgeClass() }}">{{ $s['status']->label() }}</span>
                            @if ($s['billing']?->hasDiscount())
                                <span class="ml-1 rounded bg-blue-100 px-2 py-1 text-xs text-blue-800">Diskon {{ $s['billing']->discount_percent }}%</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">{{ $s['paid'] > 0 ? 'Rp '.number_format($s['paid'], 0, ',', '.') : '-' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">{{ $s['remaining'] !== null && $s['status'] !== \App\Enums\EnrollmentStatus::Mundur ? 'Rp '.number_format($s['remaining'], 0, ',', '.') : '-' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('enrollment.show', $a) }}" class="rounded bg-slate-800 px-3 py-1 text-white hover:bg-slate-700">Buka</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-6 text-center text-slate-500">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $applicants->links() }}</div>
@endsection
