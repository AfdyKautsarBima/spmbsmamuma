@php
    $applicant->loadMissing(['documents', 'familyDetail.fatherOccupation', 'familyDetail.motherOccupation', 'familyDetail.guardianOccupation']);
    $checklist = app(\App\Services\DocumentChecklist::class);
    $statusMap = $checklist->statusMap($applicant);
    $missingList = $checklist->missing($statusMap, $checklist->absentParents($applicant));
    $kk = $applicant->familyDetail;
@endphp

<div class="mt-4 rounded-lg bg-white p-4 shadow-sm">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-sm font-medium text-slate-500">
            Berkas
            <span class="ml-2 rounded px-2 py-1 text-xs {{ $missingList === [] ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">{{ $missingList === [] ? 'Lengkap' : 'Belum lengkap' }}</span>
        </h2>
        @if (($canEdit ?? false))
            <a href="{{ route('documents.edit', $applicant) }}" class="rounded border px-3 py-1 text-sm hover:bg-slate-50">Cek berkas</a>
        @endif
    </div>

    <div class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
        @foreach (\App\Enums\DocumentType::cases() as $type)
            @php($status = $statusMap[$type->value])
            @if ($type === \App\Enums\DocumentType::SuratKematianAyah && $statusMap['ktp_ayah'] !== \App\Enums\DocumentStatus::TidakAda)
                @continue
            @endif
            @if ($type === \App\Enums\DocumentType::SuratKematianIbu && $statusMap['ktp_ibu'] !== \App\Enums\DocumentStatus::TidakAda)
                @continue
            @endif
            <div class="flex justify-between border-b border-slate-100 py-1">
                <span>
                    {{ $type->label() }}
                    @if (in_array($type, [\App\Enums\DocumentType::Kks, \App\Enums\DocumentType::Pkh], true))
                        @php($card = $applicant->documents->first(fn ($d) => $d->type === $type))
                        @if ($status === \App\Enums\DocumentStatus::Diterima && $card?->card_number)
                            <span class="text-slate-500">({{ $card->card_number }})</span>
                        @endif
                        @endif
                        @if ($type === \App\Enums\DocumentType::SuratPeringkat && $status === \App\Enums\DocumentStatus::Diterima)
                            @php($ranking = $applicant->documents->first(fn ($d) => $d->type === $type))
                            @if ($ranking?->rank_position)
                                <span class="text-slate-500">({{ ucfirst($ranking->ranking_type) }}, peringkat {{ $ranking->rank_position }})</span>
                            @endif
                        @endif
                </span>
                <span class="{{ $status === \App\Enums\DocumentStatus::Diterima ? 'text-green-700' : ($status === \App\Enums\DocumentStatus::TidakAda ? 'text-slate-500' : 'text-amber-700') }}">{{ in_array($type, [\App\Enums\DocumentType::KtpAyah, \App\Enums\DocumentType::KtpIbu], true) && $status === \App\Enums\DocumentStatus::TidakAda ? 'Tidak ada/almarhum' : $status->label() }}</span>
            </div>
        @endforeach
    </div>

    @if ($missingList)
        <p class="mt-3 text-sm text-amber-800">Kurang: {{ implode(', ', $missingList) }}.</p>
    @endif

    @php($certificate = $applicant->documents->first(fn ($d) => $d->type === \App\Enums\DocumentType::Sertifikat))
    @if ($certificate?->note)
        <p class="mt-2 text-sm text-slate-600">Sertifikat kejuaraan: {{ $certificate->note }}</p>
    @endif

    <div class="mt-4 grid gap-4 border-t pt-4 text-sm sm:grid-cols-2">
        <div>
            <p class="text-slate-500">NISN</p>
            <p class="font-medium">{{ $applicant->nisn ?? '-' }}</p>
        </div>
        @if ($kk)
            <div>
                <p class="text-slate-500">Nomor KK / NIK murid</p>
                <p class="font-medium">{{ $kk->kk_number }} / {{ $kk->student_nik }}</p>
            </div>
            <div>
                <p class="text-slate-500">Ayah</p>
                <p class="font-medium">
                    {{ $applicant->father_name }}
                    @if ($kk->father_absent) (tidak ada / almarhum)
                    @else - NIK {{ $kk->father_nik }}, lahir {{ $kk->father_birth_year }}, {{ $kk->fatherOccupation?->name }}
                    @endif
                </p>
            </div>
            <div>
                <p class="text-slate-500">Ibu</p>
                <p class="font-medium">
                    {{ $applicant->mother_name }}
                    @if ($kk->mother_absent) (tidak ada / almarhum)
                    @else - NIK {{ $kk->mother_nik }}, lahir {{ $kk->mother_birth_year }}, {{ $kk->motherOccupation?->name }}
                    @endif
                </p>
            </div>
            @if ($kk->guardian_name)
                <div class="sm:col-span-2">
                    <p class="text-slate-500">Wali</p>
                    <p class="font-medium">
                        {{ $kk->guardian_name }} ({{ $kk->guardian_relation }}) - NIK {{ $kk->guardian_nik }}, {{ $kk->guardianOccupation?->name }}{{ $kk->guardian_phone ? ', HP '.$kk->guardian_phone : '' }}
                    </p>
                </div>
            @endif
        @else
            <div>
                <p class="text-slate-500">Data KK</p>
                <p class="font-medium">Belum diisi</p>
            </div>
        @endif
    </div>
</div>
