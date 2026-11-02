<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\UniformHandover;
use App\Models\UniformType;
use Illuminate\Support\Facades\DB;

/** Angka ringkas seragam untuk dashboard. */
class UniformStats
{
    public function forYear(AcademicYear $year): array
    {
        $typeCount = UniformType::where('academic_year_id', $year->id)->where('is_active', true)->count();

        $rows = Applicant::query()
            ->where('applicants.academic_year_id', $year->id)
            ->leftJoin('uniform_entitlements as e', 'e.applicant_id', '=', 'applicants.id')
            ->selectRaw('applicants.id, applicants.withdrawn_at, e.id as ent_id, e.threshold_amount,
                (select count(*) from uniform_handovers h join uniform_types t on t.id = h.uniform_type_id
                    where h.applicant_id = applicants.id and t.is_active = 1) as handed,
                (select count(*) from uniform_handovers h2 where h2.applicant_id = applicants.id) as handed_all,
                (select coalesce(sum(p.amount), 0) from payments p where p.applicant_id = applicants.id and p.cancelled_at is null) as paid')
            ->get();

        $stats = ['entitled' => 0, 'not_picked' => 0, 'partial' => 0, 'complete' => 0,
            'withdrawn_with_uniform' => 0, 'below_threshold_with_uniform' => 0];

        foreach ($rows as $row) {
            $handedAll = (int) $row->handed_all;

            if ($row->withdrawn_at !== null) {
                $stats['withdrawn_with_uniform'] += $handedAll > 0 ? 1 : 0;
                continue;
            }
            if ($row->ent_id === null) {
                continue;
            }

            $stats['entitled']++;

            if ($handedAll === 0) {
                $stats['not_picked']++;
            } elseif ($typeCount > 0 && (int) $row->handed >= $typeCount) {
                $stats['complete']++;
            } else {
                $stats['partial']++;
            }

            if ($handedAll > 0 && (int) $row->paid < (int) $row->threshold_amount) {
                $stats['below_threshold_with_uniform']++;
            }
        }

        $yearApplicants = Applicant::where('academic_year_id', $year->id)->select('id');
        $today = UniformHandover::query()->whereDate('handed_at', today())->whereIn('applicant_id', $yearApplicants);

        return $stats + [
            'type_count'     => $typeCount,
            'handed_today'   => (clone $today)->count(),
            'students_today' => (clone $today)->distinct()->count('applicant_id'),
            'size_recap'     => $this->sizeRecap($year, $stats['entitled']),
        ];
    }

    /**
     * Kebutuhan ukuran: per jenis seragam yang memakai ukuran, jumlah murid berhak (belum mundur) per ukuran.
     *
     * @return array<string, array{sizes: array<string,int>, missing: int}>
     */
    private function sizeRecap(AcademicYear $year, int $entitledCount): array
    {
        $recap = [];
        $types = UniformType::where('academic_year_id', $year->id)->where('is_active', true)->where('needs_size', true)->ordered()->get();

        foreach ($types as $type) {
            $rows = DB::table('applicant_uniform_sizes as s')
                ->join('uniform_entitlements as e', 'e.applicant_id', '=', 's.applicant_id')
                ->join('applicants as a', 'a.id', '=', 's.applicant_id')
                ->join('uniform_sizes as z', 'z.id', '=', 's.uniform_size_id')
                ->where('s.uniform_type_id', $type->id)
                ->where('e.academic_year_id', $year->id)
                ->whereNull('a.withdrawn_at')
                ->whereNull('a.deleted_at')
                ->groupBy('z.name', 'z.sort_order', 'z.id')
                ->orderBy('z.sort_order')->orderBy('z.id')
                ->selectRaw('z.name as size_name, count(*) as total')
                ->get();

            $sizes = $rows->pluck('total', 'size_name')->map(fn ($n) => (int) $n)->all();
            $recap[$type->name] = ['sizes' => $sizes, 'missing' => max(0, $entitledCount - array_sum($sizes))];
        }

        return $recap;
    }
}
