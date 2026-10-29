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
     * Daftar yang masih kurang (teks siap tampil). Kosong berarti lengkap.
     *
     * @param  array<string, DocumentStatus>  $map
     * @return list<string>
     */
    public function missing(array $map): array
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

        $parents = ($map[DocumentType::KtpAyah->value] === DocumentStatus::Diterima
                && $map[DocumentType::KtpIbu->value] === DocumentStatus::Diterima)
            || $map[DocumentType::KtpWali->value] === DocumentStatus::Diterima;

        if (! $parents) {
            $missing[] = 'KTP orang tua/wali';
        }

        return $missing;
    }

    public function isComplete(array $map): bool
    {
        return $this->missing($map) === [];
    }

    /** Hitung ulang dari database dan simpan ke kolom documents_complete. */
    public function refresh(Applicant $applicant): bool
    {
        $applicant->unsetRelation('documents');
        $complete = $this->isComplete($this->statusMap($applicant));

        if ($applicant->documents_complete !== $complete) {
            $applicant->forceFill(['documents_complete' => $complete])->saveQuietly();
        }

        return $complete;
    }
}
