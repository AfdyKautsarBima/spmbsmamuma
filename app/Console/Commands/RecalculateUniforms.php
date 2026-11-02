<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Services\UniformService;
use Illuminate\Console\Command;

class RecalculateUniforms extends Command
{
    protected $signature = 'seragam:hitung-ulang';

    protected $description = 'Beri hak seragam kepada murid tahun ajaran aktif yang sudah mencapai ambang (hak yang ada tidak dicabut)';

    public function handle(UniformService $uniforms): int
    {
        $year = AcademicYear::active();

        if (! $year) {
            $this->error('Belum ada tahun ajaran aktif.');

            return self::FAILURE;
        }

        $granted = $uniforms->syncYear($year);
        $this->info("TA {$year->name}: {$granted} murid mendapat hak seragam baru.");

        return self::SUCCESS;
    }
}
