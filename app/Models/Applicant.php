<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Applicant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'academic_year_id', 'registration_number', 'registration_seq',
        'name', 'gender', 'birth_place', 'birth_date',
        'province_code', 'province_name', 'regency_code', 'regency_name',
        'district_code', 'district_name', 'village_code', 'village_name', 'rt', 'rw',
        'father_name', 'mother_name', 'phone', 'student_phone',
        'school_id', 'referrer_name', 'referrer_class_name', 'person_key', 'created_by',
        'nisn', 'documents_complete',
        'withdrawn_at', 'withdrawn_reason', 'withdrawn_by', 'refund_note',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'registration_seq' => 'integer', 'documents_complete' => 'boolean', 'withdrawn_at' => 'date'];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicantDocument::class);
    }

    public function familyDetail(): HasOne
    {
        return $this->hasOne(ApplicantFamilyDetail::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function billing(): HasOne
    {
        return $this->hasOne(ApplicantBilling::class);
    }

    public function uniformEntitlement(): HasOne
    {
        return $this->hasOne(UniformEntitlement::class);
    }

    public function uniformHandovers(): HasMany
    {
        return $this->hasMany(UniformHandover::class);
    }

    public function uniformSizes(): HasMany
    {
        return $this->hasMany(ApplicantUniformSize::class);
    }

    /** Filter status daftar ulang di database: terdaftar | menyicil | lunas | mundur. */
    public function scopeEnrollmentStatus(Builder $query, ?string $status): Builder
    {
        $paid = '(select coalesce(sum(p.amount), 0) from payments p where p.applicant_id = applicants.id and p.cancelled_at is null)';
        $net = '(select b.net_bill from applicant_billings b where b.applicant_id = applicants.id)';

        return match ($status) {
            'mundur'    => $query->whereNotNull('applicants.withdrawn_at'),
            'terdaftar' => $query->whereNull('applicants.withdrawn_at')->whereRaw("{$paid} = 0"),
            'menyicil'  => $query->whereNull('applicants.withdrawn_at')->whereRaw("{$paid} > 0")->whereRaw("{$paid} < {$net}"),
            'lunas'     => $query->whereNull('applicants.withdrawn_at')->whereRaw("{$paid} > 0")->whereRaw("{$paid} >= {$net}"),
            default     => $query,
        };
    }

    /** 1 -> A-001, 1000 -> A-1000 */
    public static function formatNumber(int $seq): string
    {
        return 'A-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    /** "A-001" -> 1. Null bila format salah (A-01, A-0001, B-001, A-000). */
    public static function parseNumber(string $number): ?int
    {
        if (! preg_match('/^A-(\d{3}|[1-9]\d{3,})$/', $number, $m)) {
            return null;
        }

        $seq = (int) $m[1];

        return $seq >= 1 ? $seq : null;
    }

    /** Kunci orang: nama + tanggal lahir + nama ibu, tanpa membedakan huruf besar/kecil dan spasi ganda. */
    public static function personKey(string $name, ?string $birthDate, ?string $motherName): string
    {
        $norm = fn (string $v) => trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($v)));

        return $norm($name).'|'.($birthDate ?? '').'|'.$norm($motherName ?? '');
    }

    /** Pembawa terkunci sejak pembayaran pertama yang sah. Setelah itu hanya admin yang boleh mengubahnya. */
    public function referrerLocked(): bool
    {
        return $this->payments()->whereNull('cancelled_at')->exists();
    }

    public function fullAddress(): string
    {
        $parts = [];
        if ($this->rt !== null || $this->rw !== null) {
            $parts[] = 'RT '.($this->rt ?? '-').'/RW '.($this->rw ?? '-');
        }

        foreach ([$this->village_name, $this->district_name, $this->regency_name, $this->province_name] as $part) {
            if ($part !== null && $part !== '') {
                $parts[] = $part;
            }
        }

        return $parts === [] ? '-' : implode(', ', $parts);
    }
}
