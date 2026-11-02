<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UniformHandover extends Model
{
    protected $fillable = ['applicant_id', 'uniform_type_id', 'uniform_size_id', 'handed_at', 'handed_by'];

    protected function casts(): array
    {
        return ['handed_at' => 'datetime'];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(UniformType::class, 'uniform_type_id');
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(UniformSize::class, 'uniform_size_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handed_by');
    }
}
