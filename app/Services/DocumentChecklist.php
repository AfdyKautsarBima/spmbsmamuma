<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Applicant;

/** Aturan kelengkapan berkas. Berkas yang belum punya baris dianggap "Belum diterima". */
class DocumentChecklist
{
    /** @return array<string, DocumentStatus> jenis berkas (value) => status */
    public function statusMap(Applicant $applicant): array
    {
        $documents = $applicant->relationLoaded('documents') ? $applicant->documents : $applicant->documents()->get();
        $byType = $documents->keyBy(fn ($d) => $d->type->value);

        $map = [];
        foreach (DocumentType::cases() as $type) {
            $map[$type->value] = $byType->get($type->value)?->status ?? DocumentStatus::Belum;
        }

        return $map;
    }

    /**
     * Apakah ayah/ibu ditandai tidak ada atau almarhum pada data KK.
     *
     * @return array{father: bool, mother: bool}
     */
    public function absentParents(Applicant $applicant): array
    {
        $family = $applicant->relationLoaded('familyDetail') ? $applicant->familyDetail : $applicant->familyDetail()->first();

        return ['father' => (bool) $family?->father_absent, 'mother' => (bool) $family?->mother_absent];
    }

    /**
     * Daftar yang masih kurang (teks siap tampil). Kosong berarti lengkap.
     *
     * @param  array<string, DocumentStatus>  $map
     * @param  array{father?: bool, mother?: bool}  $absent  orang tua yang ditandai tidak ada/almarhum di data KK
     * @return list<string>
     */
    public function missing(array $map, array $absent = []): array
    {
        $missing = [];

        foreach (DocumentType::cases() as $type) {
            $status = $map[$type->value];

            if ($type->group() === 'wajib' && $status !== DocumentStatus::Diterima) {
                $missing[] = $type->label();
            }

            if ($type->group() === 'kondisional' && $status === DocumentStatus::Belum) {
                $missing[] = $type->label().' (belum diputuskan)';
            }
        }

        // KTP orang tua lengkap bila salah satu terpenuhi:
        //  1. KTP wali diterima;
        //  2. KTP ayah dan ibu diterima;
        //  3. satu orang tua tidak ada/almarhum (status KTP-nya "Tidak ada" atau dicentang di data KK)
        //     dan KTP orang tua yang lain diterima.
        $father = $map[DocumentType::KtpAyah->value];
        $mother = $map[DocumentType::KtpIbu->value];
        $guardianOk = $map[DocumentType::KtpWali->value] === DocumentStatus::Diterima;

        $fatherOk = $father === DocumentStatus::Diterima;
        $motherOk = $mother === DocumentStatus::Diterima;
        $fatherGone = ! $fatherOk && ($father === DocumentStatus::TidakAda || ($absent['father'] ?? false));
        $motherGone = ! $motherOk && ($mother === DocumentStatus::TidakAda || ($absent['mother'] ?? false));

        $parentsOk = $guardianOk
            || (($fatherOk || $fatherGone) && ($motherOk || $motherGone) && ($fatherOk || $motherOk));

        if (! $parentsOk) {
            $missing[] = ($fatherGone && $motherGone) ? 'KTP wali (kedua orang tua tidak ada)' : 'KTP orang tua/wali';
        }

        return $missing;
    }

    public function isComplete(array $map, array $absent = []): bool
    {
        return $this->missing($map, $absent) === [];
    }

    /** Hitung ulang dari database dan simpan ke kolom documents_complete. */
    public function refresh(Applicant $applicant): bool
    {
        $applicant->unsetRelation('documents');
        $applicant->unsetRelation('familyDetail');
        $complete = $this->isComplete($this->statusMap($applicant), $this->absentParents($applicant));

        if ($applicant->documents_complete !== $complete) {
            $applicant->forceFill(['documents_complete' => $complete])->saveQuietly();
        }

        return $complete;
    }
}
