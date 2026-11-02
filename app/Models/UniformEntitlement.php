<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UniformEntitlement extends Model
{
    protected $fillable = ['applicant_id', 'academic_year_id', 'threshold_percent', 'threshold_amount', 'net_bill', 'granted_at'];

    protected function casts(): array
    {
        return ['threshold_percent' => 'integer', 'threshold_amount' => 'integer', 'net_bill' => 'integer', 'granted_at' => 'datetime'];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class)->withTrashed();
    }
}
