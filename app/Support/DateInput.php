<?php

namespace App\Support;

/**
 * Tanggal di layar memakai dd/mm/yyyy. Di database dan validasi tetap yyyy-mm-dd.
 */
class DateInput
{
    /** "25/12/2010" menjadi "2010-12-25". Nilai lain dikembalikan apa adanya (biar validasi yang menolak). */
    public static function toIso(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $v = trim($value);

        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $v, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return $value;
    }

    /** Tanggal valid (dd/mm/yyyy atau yyyy-mm-dd) menjadi yyyy-mm-dd. Selain itu null. Untuk filter. */
    public static function iso(?string $value): ?string
    {
        $converted = self::toIso($value);

        if ($converted !== null && preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $converted, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return $converted;
        }

        return null;
    }

    /** "2010-12-25" menjadi "25/12/2010". Teks lain (misalnya ketikan yang salah) ditampilkan apa adanya. */
    public static function display(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})#', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return "{$m[3]}/{$m[2]}/{$m[1]}";
        }

        return $value;
    }
}
