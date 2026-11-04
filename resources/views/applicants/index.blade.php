@extends('layouts.app')
@section('title', 'Pendaftar')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-xl font-semibold">Pendaftar {{ $year ? 'TA '.$year->name : '' }}</h1>
        @unless ($readOnly)
            <a href="{{ route('applicants.create') }}" class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Input Pendaftar</a>
        @endunless
    </div>

    @if ($readOnly && $year)
        <div class="mb-4 rounded border border-slate-300 bg-slate-50 p-3 text-sm text-slate-700">Tahun ajaran arsip. Data hanya bisa dilihat.</div>
    @endif

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
        @if (auth()->user()->isAdmin())
            <select name="year" class="rounded border border-slate-300 px-3 py-2 text-sm">
                @foreach ($years as $y)
                    <option value="{{ $y->id }}" @selected($year && $year->id === $y->id)>TA {{ $y->name }}{{ $y->is_active ? ' (aktif)' : '' }}</option>
                @endforeach
            </select>
        @endif
        <input name="q" value="{{ request('q') }}" placeholder="Nama, nomor, atau nama orang tua"
               class="w-64 rounded border border-slate-300 px-3 py-2 text-sm">
        <select name="school_id" class="max-w-xs rounded border border-slate-300 px-3 py-2 text-sm">
            <option value="">Semua asal sekolah</option>
            @foreach ($schools as $school)
                <option value="{{ $school->id }}" @selected((string) request('school_id') === (string) $school->id)>{{ $school->name }} ({{ $school->regency_name }})</option>
            @endforeach
        </select>
        <x-date-input name="date" :value="request('date')" class="w-36" title="Tanggal input (dd/mm/yyyy)" />
        @if (auth()->user()->isAdmin())
            <select name="status" class="rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">Data aktif</option>
                <option value="dihapus" @selected(request('status') === 'dihapus')>Data dihapus</option>
            </select>
        @endif
        <button class="rounded border px-4 py-2 text-sm hover:bg-white">Filter</button>
    </form>

    <p class="mb-2 text-sm text-slate-500">{{ $applicants->total() }} data</p>

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-slate-50 text-slate-500">
                <tr>
                    <th class="px-4 py-3">Nomor</th>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">L/P</th>
                    <th class="px-4 py-3">Asal sekolah</th>
                    <th class="px-4 py-3">Pembawa</th>
                    <th class="px-4 py-3">Desa / Kecamatan</th>
                    <th class="px-4 py-3">Berkas</th>
                    <th class="px-4 py-3">Diinput</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($applicants as $a)
                    <tr class="{{ $a->trashed() ? 'text-slate-400' : '' }}">
                        <td class="whitespace-nowrap px-4 py-3 font-medium">{{ $a->registration_number }}</td>
                        <td class="px-4 py-3">{{ $a->name }}</td>
                        <td class="px-4 py-3">{{ $a->gender }}</td>
                        <td class="px-4 py-3">{{ $a->school->name }}</td>
                        <td class="px-4 py-3">
                            {{ $a->referrer_name ?? '-' }}
                            @if ($a->referrer_class_name)<span class="text-slate-500">({{ $a->referrer_class_name }})</span>@endif
                        </td>
                        <td class="px-4 py-3">{{ $a->village_name }} / {{ $a->district_name }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded px-2 py-1 text-xs {{ $a->documents_complete ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">{{ $a->documents_complete ? 'Lengkap' : 'Belum lengkap' }}</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">{{ $a->created_at->format('d/m/Y H:i') }}</td>
                        <td class="flex justify-end gap-2 px-4 py-3">
                            <a href="{{ route('applicants.show', $a) }}" class="rounded border px-3 py-1 text-slate-800 hover:bg-slate-50">Lihat</a>
                            @if (! $a->trashed() && ! $readOnly)
                                <a href="{{ route('applicants.edit', $a) }}" class="rounded border px-3 py-1 text-slate-800 hover:bg-slate-50">Ubah</a>
                                <a href="{{ route('documents.edit', $a) }}" class="rounded border px-3 py-1 text-slate-800 hover:bg-slate-50">Berkas</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-6 text-center text-slate-500">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $applicants->links() }}</div>
@endsection
