<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    protected $fillable = ['name', 'registration_fee', 'uniform_threshold_amount', 'is_active'];

    protected function casts(): array
    {
        return [
            'registration_fee'          => 'integer',
            'uniform_threshold_amount' => 'integer',
            'is_active'                 => 'boolean',
        ];
    }

    public static function active(): ?self
    {
        return static::where('is_active', true)->first();
    }
}
