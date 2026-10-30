<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantBilling extends Model
{
    protected $fillable = [
        'applicant_id', 'academic_year_id', 'fee_snapshot', 'discount_percent', 'discount_label',
        'discount_type', 'discount_period_id', 'net_bill', 'reason', 'set_by',
    ];

    protected function casts(): array
    {
        return ['fee_snapshot' => 'integer', 'discount_percent' => 'integer', 'net_bill' => 'integer'];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class)->withTrashed();
    }

    public function hasDiscount(): bool
    {
        return $this->discount_percent > 0;
    }
}
