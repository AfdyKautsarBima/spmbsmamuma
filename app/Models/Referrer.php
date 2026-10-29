<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referrer extends Model
{
    protected $fillable = ['name', 'name_key', 'created_by'];

    public function applicants(): HasMany
    {
        return $this->hasMany(Applicant::class);
    }

    /** Rapikan penulisan: spasi ganda dibuang dan huruf diawali kapital. */
    public static function cleanName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));

        return mb_convert_case(mb_strtolower($name), MB_CASE_TITLE);
    }
}
