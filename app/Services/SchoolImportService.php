<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Region;
use App\Models\School;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use SimpleXMLElement;
use ZipArchive;

class SchoolImportService
{
    private const MAIN_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const PACKAGE_REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private const MAX_ROWS = 1000;

    private const MAX_XML_SIZE = 20 * 1024 * 1024;

    /**
     * @return int Number of imported schools
     */
    public function import(UploadedFile $file): int
    {
        $rows = $this->readRows($file->getRealPath());
        $headerIndex = $this->findHeader($rows);
        $header = $rows[$headerIndex]['cells'];
        $columns = $this->mapHeaders($header);

        if (! isset($columns['nama sekolah'], $columns['kode kabupaten/kota'])) {
            throw ValidationException::withMessages([
                'file' => 'Kolom wajib: Nama Sekolah dan Kode Kabupaten/Kota. Gunakan template Excel yang tersedia.',
            ]);
        }

        $schools = [];
        $errors = [];
        $seenNames = [];
        $seenNpsn = [];

        foreach (array_slice($rows, $headerIndex + 1) as $row) {
            $cells = $row['cells'];
            $name = $this->cell($cells, $columns['nama sekolah']);
            $npsn = isset($columns['npsn']) ? $this->cell($cells, $columns['npsn']) : '';
            $rawRegencyCode = $this->cell($cells, $columns['kode kabupaten/kota']);

            if (trim($name.$npsn.$rawRegencyCode) === '') {
                continue;
            }

            $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
            $npsn = trim($npsn);
            $regencyCode = Region::normalizeCode(trim($rawRegencyCode));

            $data = [
                'name' => $name,
                'npsn' => $npsn === '' ? null : $npsn,
                'regency_code' => $regencyCode ?? trim($rawRegencyCode),
            ];
            $validator = Validator::make($data, [
                'name' => [
                    'required', 'string', 'max:150',
                    Rule::unique('schools', 'name')->where('regency_code', $data['regency_code']),
                ],
                'npsn' => ['nullable', 'digits:8', Rule::unique('schools', 'npsn')],
                'regency_code' => ['required', Rule::exists('regions', 'code')->where('level', 2)],
            ], [
                'name.unique' => 'Sekolah dengan nama yang sama sudah ada di kabupaten/kota tersebut.',
                'npsn.unique' => 'NPSN sudah digunakan sekolah lain.',
                'regency_code.exists' => 'Kode kabupaten/kota tidak ditemukan atau bukan tingkat kabupaten/kota.',
            ]);

            $nameKey = mb_strtolower($name).'|'.$data['regency_code'];
            if (isset($seenNames[$nameKey])) {
                $validator->errors()->add('name', 'Nama sekolah dan kabupaten/kota duplikat di dalam file.');
            }
            if ($data['npsn'] !== null && isset($seenNpsn[$data['npsn']])) {
                $validator->errors()->add('npsn', 'NPSN duplikat di dalam file.');
            }

            $seenNames[$nameKey] = true;
            if ($data['npsn'] !== null) {
                $seenNpsn[$data['npsn']] = true;
            }

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $errors[] = "Baris {$row['number']}: {$error}";
                }

                continue;
            }

            $region = Region::query()->where('code', $regencyCode)->firstOrFail();
            $schools[] = $data + [
                'regency_code' => $regencyCode,
                'regency_name' => $region->name,
                'is_active' => true,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['file' => implode(' ', array_slice($errors, 0, 20))]);
        }
        if ($schools === []) {
            throw ValidationException::withMessages(['file' => 'Tidak ada data sekolah yang dapat diimpor.']);
        }

        DB::transaction(function () use ($schools): void {
            foreach ($schools as $school) {
                School::create($school);
            }

            ActivityLog::record(
                'schools.imported',
                count($schools).' sekolah ditambahkan melalui impor Excel',
                null,
                null,
                ['count' => count($schools)],
            );
        });

        return count($schools);
    }

    /**
     * @return list<array{number:int,cells:array<int,string>}>
     */
    private function readRows(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('File bukan berkas Excel .xlsx yang valid.');
        }

        try {
            $workbook = $this->xml($zip, 'xl/workbook.xml');
            $workbook->registerXPathNamespace('x', self::MAIN_NS);
            $workbook->registerXPathNamespace('r', self::REL_NS);
            $sheets = $workbook->xpath('/x:workbook/x:sheets/x:sheet');
            $relationshipId = isset($sheets[0]) ? (string) $sheets[0]->attributes(self::REL_NS)['id'] : '';
            if ($relationshipId === '') {
                throw new InvalidArgumentException('Lembar kerja pertama tidak ditemukan di file Excel.');
            }

            $relationships = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
            $relationships->registerXPathNamespace('p', self::PACKAGE_REL_NS);
            $targets = $relationships->xpath('/p:Relationships/p:Relationship[@Id="'.$relationshipId.'"]');
            $target = isset($targets[0]) ? (string) $targets[0]['Target'] : '';
            if ($target === '' || str_contains($target, '..')) {
                throw new InvalidArgumentException('Lembar kerja Excel tidak dapat dibaca.');
            }
            $sheetPath = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.ltrim($target, '/');

            $sharedStrings = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                $stringsXml = $this->xml($zip, 'xl/sharedStrings.xml');
                $stringsXml->registerXPathNamespace('x', self::MAIN_NS);
                foreach ($stringsXml->xpath('/x:sst/x:si') as $item) {
                    $sharedStrings[] = $this->text($item);
                }
            }

            $sheet = $this->xml($zip, $sheetPath);
            $sheet->registerXPathNamespace('x', self::MAIN_NS);
            $rows = [];
            foreach ($sheet->xpath('/x:worksheet/x:sheetData/x:row') as $row) {
                $number = (int) $row['r'];
                if ($number > self::MAX_ROWS + 1) {
                    throw new InvalidArgumentException('File Excel maksimal berisi '.self::MAX_ROWS.' baris data.');
                }

                $cells = [];
                $row->registerXPathNamespace('x', self::MAIN_NS);
                foreach ($row->xpath('./x:c') as $cell) {
                    $reference = (string) $cell['r'];
                    if (! preg_match('/^([A-Z]+)/', $reference, $match)) {
                        continue;
                    }
                    $column = $this->columnIndex($match[1]);
                    $type = (string) $cell['t'];
                    if ($type === 'inlineStr') {
                        $value = $this->text($cell->is);
                    } else {
                        $raw = (string) $cell->v;
                        $value = $type === 's' ? ($sharedStrings[(int) $raw] ?? '') : $raw;
                    }
                    $cells[$column] = $value;
                }
                $rows[] = ['number' => $number, 'cells' => $cells];
            }
        } finally {
            $zip->close();
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'Lembar pertama pada file Excel kosong.']);
        }

        return $rows;
    }

    private function xml(ZipArchive $zip, string $path): SimpleXMLElement
    {
        $stat = $zip->statName($path);
        if ($stat === false || $stat['size'] > self::MAX_XML_SIZE) {
            throw new InvalidArgumentException('Struktur file Excel tidak lengkap atau terlalu besar.');
        }

        $content = $zip->getFromName($path);
        $previousErrorSetting = libxml_use_internal_errors(true);
        try {
            $xml = $content === false ? false : simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS);
            if ($xml === false) {
                throw new InvalidArgumentException('Struktur file Excel tidak valid.');
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorSetting);
        }
    }

    /** @param list<array{number:int,cells:array<int,string>}> $rows */
    private function findHeader(array $rows): int
    {
        foreach ($rows as $index => $row) {
            $headers = array_map(fn ($value) => mb_strtolower(trim($value)), $row['cells']);
            if (in_array('nama sekolah', $headers, true)) {
                return $index;
            }
        }

        throw ValidationException::withMessages(['file' => 'Baris judul "Nama Sekolah" tidak ditemukan. Gunakan template Excel yang tersedia.']);
    }

    /** @param array<int,string> $headers
     * @return array<string,int>
     */
    private function mapHeaders(array $headers): array
    {
        $columns = [];
        foreach ($headers as $index => $header) {
            $key = mb_strtolower(trim($header));
            if (in_array($key, ['nama sekolah', 'npsn', 'kode kabupaten/kota'], true)) {
                $columns[$key] = $index;
            }
        }

        return $columns;
    }

    /** @param array<int,string> $cells */
    private function cell(array $cells, int $column): string
    {
        return trim($cells[$column] ?? '');
    }

    private function text(SimpleXMLElement $element): string
    {
        $text = '';
        foreach ($element->xpath('.//*[local-name()="t"]') ?: [] as $part) {
            $text .= (string) $part;
        }

        return $text;
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + ord($letter) - 64;
        }

        return $index - 1;
    }
}
