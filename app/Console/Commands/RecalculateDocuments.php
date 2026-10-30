<?php

namespace App\Console\Commands;

use App\Models\Applicant;
use App\Services\DocumentChecklist;
use Illuminate\Console\Command;

class RecalculateDocuments extends Command
{
    protected $signature = 'berkas:hitung-ulang {--tahun= : ID tahun ajaran (kosong = semua tahun)}';

    protected $description = 'Hitung ulang status kelengkapan berkas semua pendaftar (jalankan setelah aturan kelengkapan berubah)';

    public function handle(DocumentChecklist $checklist): int
    {
        $checked = 0;
        $changed = 0;

        Applicant::withTrashed()
            ->when($this->option('tahun'), fn ($q, $id) => $q->where('academic_year_id', $id))
            ->with(['documents', 'familyDetail'])
            ->chunkById(200, function ($applicants) use ($checklist, &$checked, &$changed) {
                foreach ($applicants as $applicant) {
                    $before = (bool) $applicant->documents_complete;
                    $after = $checklist->refresh($applicant);
                    $checked++;
                    if ($before !== $after) {
                        $changed++;
                    }
                }
            });

        $this->info("{$checked} pendaftar diperiksa, {$changed} berubah status kelengkapannya.");

        return self::SUCCESS;
    }
}
