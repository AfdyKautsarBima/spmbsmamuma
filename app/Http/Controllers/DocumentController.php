<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Http\Requests\DocumentRequest;
use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\ApplicantFamilyDetail;
use App\Models\Occupation;
use App\Services\DocumentChecklist;
use App\Services\EnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function __construct(private DocumentChecklist $checklist, private EnrollmentService $enrollment)
    {
    }

    /** Daftar pendaftar tahun aktif beserta status kelengkapan berkas. */
    public function index(Request $request): View
    {
        $year = AcademicYear::active();

        $applicants = Applicant::query()
            ->with([
                'school',
                'documents',
                'familyDetail',
                'billing',
                'payments' => fn ($q) => $q->whereNull('cancelled_at'),
            ])
            ->when($year, fn ($q, $y) => $q->where('academic_year_id', $y->id), fn ($q) => $q->whereRaw('1 = 0'))
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhere('registration_number', 'like', "%{$s}%")
                ->orWhere('nisn', 'like', "%{$s}%")))
            ->when($request->status === 'lengkap', fn ($q) => $q->where('documents_complete', true))
            ->when($request->status === 'belum', fn ($q) => $q->where('documents_complete', false))
            ->when($request->has('enrollment_status'), function ($query) use ($request) {
                $status = $request->input('enrollment_status');
                $query->where(function ($q) use ($status) {
                    if (in_array($status, ['terdaftar', 'menyicil', 'lunas'], true)) {
                        $q->enrollmentStatus($status);

                        return;
                    }

                    $q->enrollmentStatus('menyicil')
                        ->orWhere(fn ($paid) => $paid->enrollmentStatus('lunas'));
                });
            })
            ->orderByDesc('registration_seq')
            ->paginate(25)
            ->withQueryString();

        $missing = [];
        foreach ($applicants as $applicant) {
            $missing[$applicant->id] = $this->checklist->missing(
                $this->checklist->statusMap($applicant),
                $this->checklist->absentParents($applicant),
            );
        }

        $enrollmentStatuses = $applicants->mapWithKeys(
            fn ($applicant) => [$applicant->id => $this->enrollment->summary($applicant)['status']],
        );

        return view('documents.index', [
            'applicants' => $applicants,
            'year' => $year,
            'missing' => $missing,
            'enrollmentStatuses' => $enrollmentStatuses,
        ]);
    }

    public function edit(Applicant $applicant): View
    {
        abort_unless($applicant->academicYear()->value('is_active'), 403, 'Data tahun ajaran arsip hanya bisa dilihat.');

        $applicant->load(['documents.handler', 'familyDetail', 'school']);
        $family = $applicant->familyDetail;

        $currentOccupations = array_filter([$family?->father_occupation_id, $family?->mother_occupation_id, $family?->guardian_occupation_id]);
        $occupations = Occupation::ordered()
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $currentOccupations))
            ->get(['id', 'name']);

        return view('documents.edit', [
            'applicant'   => $applicant,
            'family'      => $family ?? new ApplicantFamilyDetail(),
            'statuses'    => $this->checklist->statusMap($applicant),
            'documents'   => $applicant->documents->keyBy(fn ($d) => $d->type->value),
            'occupations' => $occupations,
        ]);
    }

    public function update(DocumentRequest $request, Applicant $applicant): RedirectResponse
    {
        $user = $request->user();
        $v = $request->validated();
        $applicant->load(['documents', 'familyDetail']);

        $info = null;

        DB::transaction(function () use ($v, $applicant, $user, &$info) {
            $this->saveStatuses($applicant, $v, $user->id);

            if (($v['docs']['skl']['status'] ?? null) === 'diterima') {
                $this->saveNisn($applicant, $v['nisn']);
            }

            if (($v['docs']['kk']['status'] ?? null) === 'diterima') {
                $info = $this->saveFamily($applicant, $v['family']);
            }

            $this->checklist->refresh($applicant);
        });

        $message = 'Berkas '.$applicant->registration_number.' disimpan.'.($info ? ' '.$info : '');

        return $request->has('back')
            ? redirect()->route('documents.index')->with('success', $message)
            : redirect()->route('documents.edit', $applicant)->with('success', $message);
    }

    /** Simpan status tiap berkas. Hanya yang berubah yang ditulis dan dicatat. */
    private function saveStatuses(Applicant $applicant, array $v, int $userId): void
    {
        $current = $applicant->documents->keyBy(fn ($d) => $d->type->value);
        $old = [];
        $new = [];

        foreach (DocumentType::cases() as $type) {
            $row = $current->get($type->value);
            $oldStatus = $row?->status ?? DocumentStatus::Belum;
            $newStatus = DocumentStatus::from(
                $v['docs'][$type->value]['status'] ?? $row?->status?->value ?? DocumentStatus::Belum->value,
            );

            $isCertificate = $type === DocumentType::Sertifikat;
            $oldNote = $row?->note;
            $newNote = $isCertificate ? (trim((string) ($v['docs']['sertifikat']['note'] ?? '')) ?: null) : null;
            $isCard = in_array($type, [DocumentType::Kks, DocumentType::Pkh], true);
            $oldCardNumber = $row?->card_number;
            $newCardNumber = $isCard && $newStatus === DocumentStatus::Diterima
                ? (trim((string) ($v['docs'][$type->value]['card_number'] ?? '')) ?: null)
                : null;
            $isRankingCertificate = $type === DocumentType::SuratPeringkat;
            $oldRankingType = $row?->ranking_type;
            $newRankingType = $isRankingCertificate && $newStatus === DocumentStatus::Diterima
                ? ($v['docs'][$type->value]['ranking_type'] ?? null)
                : null;
            $oldRank = $row?->rank_position;
            $newRank = $isRankingCertificate && $newStatus === DocumentStatus::Diterima
                ? (int) $v['docs'][$type->value]['rank']
                : null;

            $statusChanged = $oldStatus !== $newStatus;
            $noteChanged = $isCertificate && $oldNote !== $newNote;
            $cardNumberChanged = $isCard && $oldCardNumber !== $newCardNumber;
            $rankingChanged = $isRankingCertificate
                && ($oldRankingType !== $newRankingType || $oldRank != $newRank);

            if (! $statusChanged && ! $noteChanged && ! $cardNumberChanged && ! $rankingChanged) {
                continue;
            }

            ApplicantDocument::updateOrCreate(
                ['applicant_id' => $applicant->id, 'type' => $type->value],
                [
                    'status' => $newStatus,
                    'note' => $newNote,
                    'card_number' => $newCardNumber,
                    'ranking_type' => $newRankingType,
                    'rank_position' => $newRank,
                    'handled_at' => now(),
                    'handled_by' => $userId,
                ],
            );

            if ($statusChanged) {
                $old[$type->label()] = $oldStatus->label();
                $new[$type->label()] = $newStatus->label();
            }
            if ($noteChanged) {
                $old['Keterangan sertifikat'] = $oldNote;
                $new['Keterangan sertifikat'] = $newNote;
            }
            if ($cardNumberChanged) {
                $old['Nomor '.$type->label()] = $oldCardNumber;
                $new['Nomor '.$type->label()] = $newCardNumber;
            }
            if ($rankingChanged) {
                $old['Jenis peringkat surat keterangan'] = $oldRankingType;
                $new['Jenis peringkat surat keterangan'] = $newRankingType;
                $old['Peringkat surat keterangan peringkat'] = $oldRank;
                $new['Peringkat surat keterangan peringkat'] = $newRank;
            }
        }

        if ($new) {
            ActivityLog::record('documents.updated', "Status berkas {$applicant->registration_number} diperbarui", $applicant, $old, $new);
        }
    }

    private function saveNisn(Applicant $applicant, string $nisn): void
    {
        if ($applicant->nisn === $nisn) {
            return;
        }

        $old = $applicant->nisn;
        $applicant->nisn = $nisn;
        $applicant->save();

        ActivityLog::record('applicant.nisn_changed', "NISN {$applicant->registration_number} diisi", $applicant, ['nisn' => $old], ['nisn' => $nisn]);
    }

    /** Simpan data KK dan wali. Mengembalikan catatan bila nomor KK sama dengan pendaftar lain. */
    private function saveFamily(Applicant $applicant, array $kk): ?string
    {
        // Nama orang tua dikoreksi sesuai KK. Tetap satu kolom di data pendaftar.
        $applicant->father_name = $kk['father_name'];
        $applicant->mother_name = $kk['mother_name'];
        if ($applicant->isDirty(['father_name', 'mother_name'])) {
            $applicant->person_key = Applicant::personKey($applicant->name, $applicant->birth_date?->format('Y-m-d'), $applicant->mother_name);
            [$old, $new] = ActivityLog::changes($applicant, ['person_key', 'updated_at']);
            $applicant->save();
            ActivityLog::record('applicant.updated', "Nama orang tua {$applicant->registration_number} dikoreksi sesuai KK", $applicant, $old, $new);
        }

        $fatherAbsent = ! empty($kk['father_absent']);
        $motherAbsent = ! empty($kk['mother_absent']);
        $absent = (int) $fatherAbsent + (int) $motherAbsent;
        $guardianFilled = $absent >= 1 && filled($kk['guardian_name'] ?? null);

        $attributes = [
            'academic_year_id'       => $applicant->academic_year_id,
            'kk_number'              => $kk['number'],
            'student_nik'            => $kk['student_nik'],
            'father_absent'          => $fatherAbsent,
            'father_nik'             => $fatherAbsent ? null : $kk['father_nik'],
            'father_birth_year'      => $fatherAbsent ? null : (int) $kk['father_birth_year'],
            'father_occupation_id'   => $fatherAbsent ? null : (int) $kk['father_occupation_id'],
            'mother_absent'          => $motherAbsent,
            'mother_nik'             => $motherAbsent ? null : $kk['mother_nik'],
            'mother_birth_year'      => $motherAbsent ? null : (int) $kk['mother_birth_year'],
            'mother_occupation_id'   => $motherAbsent ? null : (int) $kk['mother_occupation_id'],
            'guardian_name'          => $guardianFilled ? $kk['guardian_name'] : null,
            'guardian_nik'           => $guardianFilled ? $kk['guardian_nik'] : null,
            'guardian_birth_year'    => $guardianFilled && filled($kk['guardian_birth_year'] ?? null) ? (int) $kk['guardian_birth_year'] : null,
            'guardian_relation'      => $guardianFilled ? $kk['guardian_relation'] : null,
            'guardian_occupation_id' => $guardianFilled ? (int) $kk['guardian_occupation_id'] : null,
            'guardian_phone'         => $guardianFilled ? ($kk['guardian_phone'] ?? null) : null,
        ];

        $detail = $applicant->familyDetail ?? new ApplicantFamilyDetail(['applicant_id' => $applicant->id]);
        $wasNew = ! $detail->exists;
        $detail->fill($attributes);
        [$old, $new] = ActivityLog::changes($detail, ['updated_at', 'created_at', 'applicant_id', 'academic_year_id']);
        $detail->save();

        if ($new) {
            ActivityLog::record(
                $wasNew ? 'family.created' : 'family.updated',
                'Data KK '.$applicant->registration_number.($wasNew ? ' diisi' : ' diubah'),
                $applicant,
                $old,
                $new,
            );
        }

        $shared = ApplicantFamilyDetail::query()
            ->where('academic_year_id', $applicant->academic_year_id)
            ->where('kk_number', $kk['number'])
            ->where('applicant_id', '!=', $applicant->id)
            ->with('applicant')
            ->get();

        return $shared->isEmpty() ? null : 'Catatan: nomor KK sama dengan '
            .$shared->map(fn ($d) => $d->applicant->registration_number.' '.$d->applicant->name)->join(', ')
            .' (kemungkinan saudara kandung).';
    }
}
