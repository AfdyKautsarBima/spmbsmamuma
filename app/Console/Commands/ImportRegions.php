<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportRegions extends Command
{
    protected $signature = 'wilayah:import
        {file : Path ke wilayah.sql (cahyadsn/wilayah) atau file CSV (kolom pertama kode, kolom terakhir nama)}
        {--replace : Kosongkan tabel wilayah sebelum impor}';

    protected $description = 'Impor data kode wilayah Indonesia (provinsi sampai desa/kelurahan)';

    public function handle(): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("File tidak ditemukan atau tidak bisa dibaca: {$path}");

            return self::FAILURE;
        }

        $raw = str_ends_with(strtolower($path), '.sql') ? $this->parseSql($path) : $this->parseCsv($path);

        $items = [];
        $skipped = 0;
        foreach ($raw as [$rawCode, $rawName]) {
            $code = Region::normalizeCode($rawCode);
            $name = trim(preg_replace('/\s+/u', ' ', $rawName));

            if ($code === null || $name === '') {
                $skipped++;
                continue;
            }

            $items[$code] = [
                'code'        => $code,
                'name'        => mb_substr($name, 0, 255),
                'level'       => Region::levelOf($code),
                'parent_code' => Region::parentOf($code),
            ];
        }

        if ($items === []) {
            $this->error('Tidak ada baris wilayah yang terbaca. Periksa format file (lihat panduan).');

            return self::FAILURE;
        }

        DB::transaction(function () use ($items) {
            if ($this->option('replace')) {
                Region::query()->delete();
            }
            foreach (array_chunk(array_values($items), 500) as $chunk) {
                Region::upsert($chunk, ['code'], ['name', 'level', 'parent_code']);
            }
        });

        $counts = Region::query()->selectRaw('level, count(*) as total')->groupBy('level')->pluck('total', 'level');
        $rows = [];
        foreach (Region::LEVELS as $level => $label) {
            $rows[] = [$label, number_format($counts[$level] ?? 0, 0, ',', '.')];
        }
        $this->table(['Tingkat', 'Jumlah'], $rows);

        $orphans = Region::query()
            ->whereNotNull('parent_code')
            ->whereNotIn('parent_code', Region::query()->select('code'))
            ->count();

        $this->info(count($items).' baris diimpor'.($skipped ? ", {$skipped} baris dilewati" : '').'.');
        if ($orphans > 0) {
            $this->warn("{$orphans} wilayah tidak punya induk (kemungkinan file tidak lengkap).");
        }

        ActivityLog::record('regions.imported', 'Data wilayah diimpor: '.count($items).' baris dari '.basename($path));

        return self::SUCCESS;
    }

    /** @return list<array{0:string,1:string}> */
    private function parseSql(string $path): array
    {
        $content = file_get_contents($path);
        $pattern = '~\(\s*\'(\d{2}(?:\.\d{2}){0,2}(?:\.\d{4})?)\'\s*,\s*\'((?:[^\'\\\\]++|\\\\.|\'\')*+)\'\s*[,)]~';

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        return array_map(
            fn ($m) => [$m[1], strtr($m[2], ["\\'" => "'", "''" => "'", '\\\\' => '\\'])],
            $matches,
        );
    }

    /** @return list<array{0:string,1:string}> */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $first = fgets($handle);
        rewind($handle);

        $delimiter = ',';
        foreach ([';', "\t"] as $candidate) {
            if (substr_count((string) $first, $candidate) > substr_count((string) $first, $delimiter)) {
                $delimiter = $candidate;
            }
        }

        $rows = [];
        while (($cols = fgetcsv($handle, 0, $delimiter)) !== false) {
            $cols = array_values(array_filter(array_map('trim', $cols), fn ($c) => $c !== ''));
            if (count($cols) < 2) {
                continue;
            }
            $rows[] = [ltrim($cols[0], "\xEF\xBB\xBF"), end($cols)];
        }
        fclose($handle);

        return $rows;
    }
}
