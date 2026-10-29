<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = 'code';
    protected $keyType = 'string';
    protected $fillable = ['code', 'name', 'level', 'parent_code'];

    public const LEVELS = [1 => 'Provinsi', 2 => 'Kabupaten/Kota', 3 => 'Kecamatan', 4 => 'Desa/Kelurahan'];

    /**
     * Ubah kode apa pun ("3319", "33.19", "33.19.01.2001", "3319012001") ke format bertitik.
     * Mengembalikan null bila panjangnya bukan 2, 4, 6, atau 10 digit.
     */
    public static function normalizeCode(string $raw): ?string
    {
        $digits = preg_replace('/[\s.]/', '', $raw);

        if ($digits === '' || ! ctype_digit($digits)) {
            return null;
        }

        return match (strlen($digits)) {
            2       => $digits,
            4       => substr($digits, 0, 2).'.'.substr($digits, 2, 2),
            6       => substr($digits, 0, 2).'.'.substr($digits, 2, 2).'.'.substr($digits, 4, 2),
            10      => substr($digits, 0, 2).'.'.substr($digits, 2, 2).'.'.substr($digits, 4, 2).'.'.substr($digits, 6, 4),
            default => null,
        };
    }

    public static function levelOf(string $code): int
    {
        return substr_count($code, '.') + 1;
    }

    public static function parentOf(string $code): ?string
    {
        $pos = strrpos($code, '.');

        return $pos === false ? null : substr($code, 0, $pos);
    }
}
