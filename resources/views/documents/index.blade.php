@extends('layouts.app')
@section('title', 'Cek Berkas')

@section('content')
    <h1 class="mb-4 text-xl font-semibold">Cek Berkas {{ $year ? 'TA '.$year->name : '' }}</h1>

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Nama, nomor pendaftaran, atau NISN"
               class="w-72 rounded border border-slate-300 px-3 py-2 text-sm">
        <select name="status" class="rounded border border-slate-300 px-3 py-2 text-sm">
            <option value="">Semua status</option>
            <option value="lengkap" @selected(request('status') === 'lengkap')>Lengkap</option>
            <option value="belum" @selected(request('status') === 'belum')>Belum lengkap</option>
        </select>
        <select name="enrollment_status" class="rounded border border-slate-300 px-3 py-2 text-sm">
            <option value="terdaftar" @selected(request('enrollment_status') === 'terdaftar')>Terdaftar</option>
            <option value="">Semua (menyicil & lunas)</option>
            @foreach ([\App\Enums\EnrollmentStatus::Menyicil, \App\Enums\EnrollmentStatus::Lunas] as $enrollmentStatus)
                <option value="{{ $enrollmentStatus->value }}" @selected(request('enrollment_status') === $enrollmentStatus->value)>{{ $enrollmentStatus->label() }}</option>
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
                    <th class="px-4 py-3">NISN</th>
                    <th class="px-4 py-3">Status berkas</th>
                    <th class="px-4 py-3">Status daftar ulang</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y align-top">
                @forelse ($applicants as $a)
                    @php($lack = $missing[$a->id] ?? [])
                    @php($enrollmentStatus = $enrollmentStatuses[$a->id])
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 font-medium">{{ $a->registration_number }}</td>
                        <td class="px-4 py-3">{{ $a->name }}</td>
                        <td class="px-4 py-3">{{ $a->school->name }}</td>
                        <td class="px-4 py-3">{{ $a->nisn ?? '-' }}</td>
                        <td class="px-4 py-3">
                            @if ($a->documents_complete)
                                <span class="rounded bg-green-100 px-2 py-1 text-xs text-green-800">Lengkap</span>
                            @else
                                <span class="rounded bg-amber-100 px-2 py-1 text-xs text-amber-800">Belum lengkap</span>
                                <p class="mt-1 max-w-md text-xs text-slate-500">Kurang: {{ implode(', ', array_slice($lack, 0, 4)) }}{{ count($lack) > 4 ? ' dan '.(count($lack) - 4).' lainnya' : '' }}</p>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <span class="rounded px-2 py-1 text-xs {{ $enrollmentStatus->badgeClass() }}">{{ $enrollmentStatus->label() }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('documents.edit', $a) }}" class="rounded bg-slate-800 px-3 py-1 text-white hover:bg-slate-700">Cek berkas</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $applicants->links() }}</div>
@endsection
