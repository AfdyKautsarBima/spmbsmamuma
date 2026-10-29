<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Applicant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'academic_year_id', 'registration_number', 'registration_seq',
        'name', 'gender', 'birth_place', 'birth_date',
        'province_code', 'province_name', 'regency_code', 'regency_name',
        'district_code', 'district_name', 'village_code', 'village_name', 'rt', 'rw',
        'father_name', 'mother_name', 'phone',
        'school_id', 'referrer_id', 'person_key', 'created_by',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'registration_seq' => 'integer'];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Referrer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
    public static function personKey(string $name, string $birthDate, string $motherName): string
    {
        $norm = fn (string $v) => trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($v)));

        return $norm($name).'|'.$birthDate.'|'.$norm($motherName);
    }

    /**
     * Pembawa terkunci sejak pembayaran pertama yang sah (hanya admin yang boleh mengubah).
     * Pembayaran baru dibuat di tahap 5, jadi untuk sekarang belum ada yang terkunci.
     */
    public function referrerLocked(): bool
    {
        return false;
    }

    public function fullAddress(): string
    {
        return "RT {$this->rt}/RW {$this->rw}, {$this->village_name}, {$this->district_name}, {$this->regency_name}, {$this->province_name}";
    }
}
