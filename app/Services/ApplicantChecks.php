<?php

namespace App\Services;

use App\Models\Applicant;

/** Petunjuk nomor dan peringatan (bukan blokir) saat input pendaftar. */
class ApplicantChecks
{
    /** Nomor urut tertinggi yang pernah dipakai, termasuk data yang sudah dihapus. */
    public function lastSeq(int $yearId): int
    {
        return (int) Applicant::withTrashed()->where('academic_year_id', $yearId)->max('registration_seq');
    }

    /** @return array{last: ?string, next: string} */
    public function hint(int $yearId): array
    {
        $last = $this->lastSeq($yearId);

        return [
            'last' => $last > 0 ? Applicant::formatNumber($last) : null,
            'next' => Applicant::formatNumber($last + 1),
        ];
    }

    /**
     * @return list<array{type: string, message: string, applicants: list<array{id:int,number:string,name:string}>}>
     */
    public function warnings(int $yearId, int $seq, string $personKey, ?int $ignoreId, bool $checkJump): array
    {
        $warnings = [];

        if ($checkJump) {
            $last = $this->lastSeq($yearId);
            if ($seq > $last + 1) {
                $warnings[] = [
                    'type'       => 'jump',
                    'message'    => $last > 0
                        ? 'Nomor '.Applicant::formatNumber($seq).' melompat dari nomor terakhir '.Applicant::formatNumber($last).' (berikutnya seharusnya '.Applicant::formatNumber($last + 1).').'
                        : 'Belum ada pendaftar. Nomor pertama biasanya '.Applicant::formatNumber(1).', sedangkan Anda mengisi '.Applicant::formatNumber($seq).'.',
                    'applicants' => [],
                ];
            }
        }

        $same = Applicant::query()
            ->where('academic_year_id', $yearId)
            ->where('person_key', $personKey)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->get(['id', 'registration_number', 'name']);

        if ($same->isNotEmpty()) {
            $warnings[] = [
                'type'       => 'duplicate',
                'message'    => 'Sudah ada pendaftar dengan nama, tanggal lahir, dan nama ibu yang sama. Pastikan ini bukan data ganda (anak kembar atau nama kebetulan sama boleh tetap disimpan).',
                'applicants' => $same->map(fn ($a) => ['id' => $a->id, 'number' => $a->registration_number, 'name' => $a->name])->all(),
            ];
        }

        return $warnings;
    }
}
