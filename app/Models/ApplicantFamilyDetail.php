<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantFamilyDetail extends Model
{
    protected $fillable = [
        'applicant_id', 'academic_year_id', 'kk_number', 'student_nik',
        'father_absent', 'father_nik', 'father_birth_year', 'father_occupation_id',
        'mother_absent', 'mother_nik', 'mother_birth_year', 'mother_occupation_id',
        'guardian_name', 'guardian_nik', 'guardian_relation', 'guardian_occupation_id', 'guardian_phone',
    ];

    protected function casts(): array
    {
        return [
            'father_absent'     => 'boolean',
            'mother_absent'     => 'boolean',
            'father_birth_year' => 'integer',
            'mother_birth_year' => 'integer',
        ];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class)->withTrashed();
    }

    public function fatherOccupation(): BelongsTo
    {
        return $this->belongsTo(Occupation::class, 'father_occupation_id');
    }

    public function motherOccupation(): BelongsTo
    {
        return $this->belongsTo(Occupation::class, 'mother_occupation_id');
    }

    public function guardianOccupation(): BelongsTo
    {
        return $this->belongsTo(Occupation::class, 'guardian_occupation_id');
    }
}
