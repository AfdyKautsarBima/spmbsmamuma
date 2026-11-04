@extends('layouts.app')
@section('title', 'Pendaftar '.$applicant->registration_number)

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-xl font-semibold">
            {{ $applicant->registration_number }} - {{ $applicant->name }}
            @if ($applicant->trashed())<span class="ml-2 rounded bg-red-100 px-2 py-1 text-xs text-red-700">Dihapus</span>@endif
        </h1>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('applicants.print', $applicant) }}" target="_blank" class="rounded border bg-white px-4 py-2 text-sm hover:bg-slate-50">Cetak bukti</a>
            @if (! $applicant->trashed() && $enrollmentSummary['status']->value === 'terdaftar' && $whatsappUrl)
                <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" class="rounded border bg-white px-4 py-2 text-sm hover:bg-slate-50">Hubungi via WhatsApp</a>
            @endif
            @if ($canEdit)
                <a href="{{ route('applicants.edit', $applicant) }}" class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Ubah</a>
            @endif
        </div>
    </div>

    @unless ($applicant->academicYear->is_active)
        <div class="mb-4 rounded border border-slate-300 bg-slate-50 p-3 text-sm text-slate-700">Tahun ajaran arsip ({{ $applicant->academicYear->name }}). Data hanya bisa dilihat.</div>
    @endunless

    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-lg bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-medium text-slate-500">Identitas</h2>
            <dl class="space-y-2 text-sm">
                @foreach ([
                    'Nomor pendaftaran' => $applicant->registration_number,
                    'Tahun ajaran' => $applicant->academicYear->name,
                    'Nama murid' => $applicant->name,
                    'Jenis kelamin' => $applicant->gender === 'L' ? 'Laki-laki' : 'Perempuan',
                    'Tempat, tanggal lahir' => $applicant->birth_place.', '.$applicant->birth_date->format('d/m/Y'),
                    'Nomor HP siswa' => $applicant->student_phone ?? '-',
                    'Alamat' => $applicant->fullAddress(),
                ] as $label => $value)
                    <div><dt class="text-slate-500">{{ $label }}</dt><dd class="font-medium">{{ $value }}</dd></div>
                @endforeach
            </dl>
        </div>
        <div class="rounded-lg bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-medium text-slate-500">Keluarga dan sekolah</h2>
            <dl class="space-y-2 text-sm">
                @foreach ([
                    'Nama ayah' => $applicant->father_name,
                    'Nama ibu' => $applicant->mother_name,
                    'Nomor HP orang tua/wali' => $applicant->phone ?? '-',
                    'Asal sekolah' => $applicant->school->name.' ('.$applicant->school->regency_name.')',
                    'Nama pembawa' => $applicant->referrer_name ?? '-',
                    'Kelas pembawa' => $applicant->referrer_class_name ?? '-',
                    'Diinput oleh' => $applicant->creator->name.', '.$applicant->created_at->format('d/m/Y H:i'),
                ] as $label => $value)
                    <div><dt class="text-slate-500">{{ $label }}</dt><dd class="font-medium">{{ $value }}</dd></div>
                @endforeach
            </dl>
        </div>
    </div>

    @include('documents.summary', ['applicant' => $applicant, 'canEdit' => $canEdit])

    @include('enrollment.status-card', ['applicant' => $applicant, 'enrollmentSummary' => $enrollmentSummary])

    @if (auth()->user()->isAdmin())
        <div class="mt-4 rounded-lg bg-white p-4 shadow-sm">
            @if ($applicant->trashed())
                <form method="POST" action="{{ route('applicants.restore', $applicant) }}" class="flex flex-wrap items-center gap-2">
                    @csrf @method('PATCH')
                    <input name="reason" required minlength="3" placeholder="Alasan memulihkan" class="w-72 rounded border border-slate-300 px-3 py-2 text-sm">
                    <button class="rounded border px-4 py-2 text-sm hover:bg-slate-50">Pulihkan data</button>
                </form>
            @else
                <form method="POST" action="{{ route('applicants.destroy', $applicant) }}" class="flex flex-wrap items-center gap-2"
                      onsubmit="return confirm('Hapus pendaftar {{ $applicant->registration_number }}? Data tidak hilang permanen dan bisa dipulihkan admin.')">
                    @csrf @method('DELETE')
                    <input name="reason" required minlength="3" placeholder="Alasan menghapus" class="w-72 rounded border border-slate-300 px-3 py-2 text-sm">
                    <button class="rounded border border-red-300 px-4 py-2 text-sm text-red-700 hover:bg-red-50">Hapus data</button>
                </form>
            @endif
        </div>
    @endif

    <div class="mt-4 rounded-lg bg-white p-4 shadow-sm">
        <h2 class="mb-3 text-sm font-medium text-slate-500">Riwayat</h2>
        <ul class="divide-y text-sm">
            @forelse ($history as $log)
                <li class="py-2">
                    <span class="text-slate-500">{{ $log->created_at->format('d/m/Y H:i') }}</span>
                    <span class="font-medium">{{ $log->user?->username ?? '-' }}</span>:
                    {{ $log->description }}
                    @if ($log->reason)<span class="text-slate-500">(alasan: {{ $log->reason }})</span>@endif
                    @foreach (($log->new_values ?? []) as $key => $value)
                        <div class="ml-4 text-xs">
                            <span class="text-slate-500">{{ $key }}:</span>
                            @if (array_key_exists($key, $log->old_values ?? []))
                                <span class="text-red-700 line-through">{{ is_scalar($log->old_values[$key]) ? $log->old_values[$key] : 'kosong' }}</span> →
                            @endif
                            <span class="text-green-700">{{ is_scalar($value) ? $value : 'kosong' }}</span>
                        </div>
                    @endforeach
                </li>
            @empty
                <li class="py-2 text-slate-500">Belum ada riwayat.</li>
            @endforelse
        </ul>
    </div>
@endsection
