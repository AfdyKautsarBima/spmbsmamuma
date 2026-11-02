<?php

namespace App\Support;

use App\Reports\Report;
use RuntimeException;
use ZipArchive;

/**
 * Penulis berkas .xlsx tanpa paket tambahan (memakai ZipArchive bawaan PHP).
 * Satu lembar "Laporan" berisi judul, lalu semua bagian laporan ditumpuk ke bawah.
 */
class XlsxWriter
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    // Indeks gaya (lihat styles.xml di bawah)
    private const S_DEFAULT = 0;
    private const S_BOLD = 1;
    private const S_HEADER = 2;
    private const S_NUMBER = 3;
    private const S_BOLD_NUMBER = 4;
    private const S_TITLE = 5;

    public static function toString(Report $report): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak bisa membuat berkas Excel.');
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::rootRels());
        $zip->addFromString('xl/workbook.xml', self::workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels());
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheet($report));
        $zip->close();

        $content = file_get_contents($path);
        @unlink($path);

        return $content;
    }

    public static function sheet(Report $report): string
    {
        $rows = [];      // nomor baris => xml sel
        $widths = [];    // indeks kolom => lebar karakter
        $r = 1;

        $put = function (int $row, int $col, mixed $value, int $style) use (&$rows, &$widths) {
            if ($value === null || $value === '') {
                return;
            }
            $ref = self::columnLetter($col).$row;

            if (is_int($value) || is_float($value)) {
                $rows[$row][] = '<c r="'.$ref.'" s="'.$style.'"><v>'.$value.'</v></c>';
                $len = strlen((string) $value) + 2;
            } else {
                $text = self::escape((string) $value);
                $rows[$row][] = '<c r="'.$ref.'" t="inlineStr" s="'.$style.'"><is><t xml:space="preserve">'.$text.'</t></is></c>';
                $len = mb_strlen((string) $value);
            }
            $widths[$col] = max($widths[$col] ?? 8, min(60, $len + 2));
        };

        $put($r++, 0, $report->title, self::S_TITLE);
        if ($report->subtitle !== '') {
            $put($r++, 0, $report->subtitle, self::S_DEFAULT);
        }
        $r++;

        foreach ($report->sections as $section) {
            if ($section['title']) {
                $put($r++, 0, $section['title'], self::S_BOLD);
            }

            foreach ($section['columns'] as $i => $column) {
                $put($r, $i, $column['label'], self::S_HEADER);
            }
            $r++;

            foreach ($section['rows'] as $row) {
                foreach ($row as $i => $value) {
                    $cell = Report::cell($value, $section['columns'][$i]['type'] ?? 'text');
                    $put($r, $i, self::value($cell), Report::isNumeric($cell) ? self::S_NUMBER : self::S_DEFAULT);
                }
                $r++;
            }

            if ($section['footer']) {
                foreach ($section['footer'] as $i => $value) {
                    $cell = Report::cell($value, $section['columns'][$i]['type'] ?? 'text');
                    $put($r, $i, self::value($cell), Report::isNumeric($cell) ? self::S_BOLD_NUMBER : self::S_BOLD);
                }
                $r++;
            }

            $r++; // baris kosong antar bagian
        }

        foreach ($report->notes as $note) {
            $put($r++, 0, $note, self::S_DEFAULT);
        }

        $cols = '';
        ksort($widths);
        foreach ($widths as $i => $width) {
            $n = $i + 1;
            $cols .= '<col min="'.$n.'" max="'.$n.'" width="'.$width.'" customWidth="1"/>';
        }

        $sheetData = '';
        ksort($rows);
        foreach ($rows as $number => $cells) {
            $sheetData .= '<row r="'.$number.'">'.implode('', $cells).'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="'.self::NS.'">'
            .($cols !== '' ? '<cols>'.$cols.'</cols>' : '')
            .'<sheetData>'.$sheetData.'</sheetData>'
            .'</worksheet>';
    }

    /** Angka ditulis sebagai angka (agar bisa dijumlah). Selain itu sebagai teks. */
    private static function value(array $cell): mixed
    {
        if (Report::isNumeric($cell)) {
            return (int) $cell['v'];
        }

        return $cell['v'];
    }

    public static function columnLetter(int $index): string
    {
        $letters = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letters = chr(65 + (($n - 1) % 26)).$letters;
        }

        return $letters;
    }

    /** Buang karakter yang tidak boleh ada di XML 1.0 lalu escape. */
    private static function escape(string $text): string
    {
        $clean = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text) ?? '';

        return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private static function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private static function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="'.self::NS.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Laporan" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private static function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="'.self::NS.'">'
            .'<fonts count="3">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="14"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="3">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFE2E8F0"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="6">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="3" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
