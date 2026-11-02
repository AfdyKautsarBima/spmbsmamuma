<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantUniformSize extends Model
{
    protected $fillable = ['applicant_id', 'uniform_type_id', 'uniform_size_id', 'set_by'];

    public function size(): BelongsTo
    {
        return $this->belongsTo(UniformSize::class, 'uniform_size_id');
    }
}
