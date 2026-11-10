<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountPeriod extends Model
{
    protected $fillable = [
        'academic_year_id', 'name', 'starts_on', 'ends_on', 'percent', 'payable_amount', 'uniform_threshold_amount',
        'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date', 'ends_on' => 'date', 'percent' => 'integer', 'payable_amount' => 'integer',
            'uniform_threshold_amount' => 'integer', 'is_active' => 'boolean',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
