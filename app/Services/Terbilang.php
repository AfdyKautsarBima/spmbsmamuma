<?php

namespace App\Services;

/** Angka menjadi kalimat bahasa Indonesia, untuk kolom "terbilang" pada kwitansi. */
class Terbilang
{
    private const UNITS = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

    public static function rupiah(int $amount): string
    {
        return self::make($amount).' rupiah';
    }

    public static function make(int $n): string
    {
        if ($n === 0) {
            return 'nol';
        }

        return trim(self::words($n));
    }

    private static function words(int $n): string
    {
        $rest = fn (int $remainder) => $remainder > 0 ? ' '.self::words($remainder) : '';

        return match (true) {
            $n < 12            => self::UNITS[$n],
            $n < 20            => self::words($n - 10).' belas',
            $n < 100           => self::words(intdiv($n, 10)).' puluh'.$rest($n % 10),
            $n < 200           => 'seratus'.$rest($n - 100),
            $n < 1000          => self::words(intdiv($n, 100)).' ratus'.$rest($n % 100),
            $n < 2000          => 'seribu'.$rest($n - 1000),
            $n < 1_000_000     => self::words(intdiv($n, 1000)).' ribu'.$rest($n % 1000),
            $n < 1_000_000_000 => self::words(intdiv($n, 1_000_000)).' juta'.$rest($n % 1_000_000),
            $n < 1_000_000_000_000 => self::words(intdiv($n, 1_000_000_000)).' miliar'.$rest($n % 1_000_000_000),
            default            => self::words(intdiv($n, 1_000_000_000_000)).' triliun'.$rest($n % 1_000_000_000_000),
        };
    }
}
