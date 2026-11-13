<?php

namespace App\Http\Requests;

use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\Region;
use App\Support\DateInput;
use App\Support\ReferrerClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ApplicantRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->route('applicant') && ! AcademicYear::active()) {
            abort(403, 'Belum ada tahun ajaran aktif. Minta admin mengaktifkannya.');
        }

        return true; // peran dibatasi middleware di route
    }

    protected function prepareForValidation(): void
    {
        $collapse = fn ($v) => trim(preg_replace('/\s+/u', ' ', (string) $v));
        $optionalText = fn ($v) => ($value = $collapse($v)) === '' ? null : $value;

        $number = strtoupper(preg_replace('/\s+/', '', (string) $this->input('registration_number')));
        if (preg_match('/^A(\d+)$/', $number, $m)) {
            $number = 'A-'.$m[1];
        }
        if ($number === '') {
            /** @var Applicant|null $applicant */
            $applicant = $this->route('applicant');
            if ($applicant) {
                $number = $applicant->registration_number;
            } elseif ($yearId = AcademicYear::active()?->id) {
                $lastSeq = Applicant::withTrashed()->where('academic_year_id', $yearId)->max('registration_seq');
                $number = Applicant::formatNumber((int) $lastSeq + 1);
            }
        }

        $phone = preg_replace('/[\s\-.]/', '', (string) $this->input('phone'));
        $studentPhone = preg_replace('/[\s\-.]/', '', (string) $this->input('student_phone'));
        $rt = trim((string) $this->input('rt'));
        $rw = trim((string) $this->input('rw'));

        $this->merge([
            'registration_number' => $number,
            'birth_date'          => DateInput::toIso($this->input('birth_date')),
            'name'                => $collapse($this->input('name')),
            'birth_place'         => $optionalText($this->input('birth_place')),
            'father_name'         => $optionalText($this->input('father_name')),
            'mother_name'         => $optionalText($this->input('mother_name')),
            'phone'               => $phone === '' ? null : $phone,
            'student_phone'       => $studentPhone === '' ? null : $studentPhone,
            'rt'                  => $rt === '' ? null : $rt,
            'rw'                  => $rw === '' ? null : $rw,
        ]);
    }

    public function rules(): array
    {
        /** @var Applicant|null $applicant */
        $applicant = $this->route('applicant');
        $yearId = $applicant?->academic_year_id ?? AcademicYear::active()?->id;
        $currentSchool = $applicant?->school_id;
        $code = ['nullable', 'string', 'regex:/^\d{2}(\.\d{2}){0,2}(\.\d{4})?$/'];

        return [
            'registration_number' => [
                'required',
                'regex:/^A-(\d{3}|[1-9]\d{3,})$/',
                function (string $attribute, mixed $value, \Closure $fail) use ($yearId, $applicant) {
                    $seq = Applicant::parseNumber((string) $value);
                    if ($seq === null) {
                        $fail('Nomor pendaftaran tidak valid.');

                        return;
                    }
                    $existing = Applicant::withTrashed()
                        ->where('academic_year_id', $yearId)
                        ->where('registration_seq', $seq)
                        ->when($applicant, fn ($q) => $q->where('id', '!=', $applicant->id))
                        ->first();

                    if ($existing) {
                        $fail($existing->trashed()
                            ? "Nomor {$value} dipakai oleh data yang sudah dihapus. Minta admin memulihkannya, atau pakai nomor lain."
                            : "Nomor {$value} sudah dipakai oleh {$existing->name}.");
                    }
                },
            ],
            'name'          => ['required', 'string', 'max:100'],
            'gender'        => ['required', 'in:L,P'],
            'birth_place'   => ['nullable', 'string', 'max:100'],
            'birth_date'    => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after:1950-01-01'],
            'province_code' => $code,
            'regency_code'  => $code,
            'district_code' => $code,
            'village_code'  => array_merge($code, [Rule::exists('regions', 'code')->where('level', 4)]),
            'rt'            => ['nullable', 'regex:/^\d{1,3}$/'],
            'rw'            => ['nullable', 'regex:/^\d{1,3}$/'],
            'father_name'   => ['nullable', 'string', 'max:100'],
            'mother_name'   => ['nullable', 'string', 'max:100'],
            'phone'         => ['nullable', 'regex:/^08\d{8,11}$/'],
            'student_phone' => ['nullable', 'regex:/^08\d{8,11}$/'],
            'school_id'     => ['required', Rule::exists('schools', 'id')->where(function ($q) use ($currentSchool) {
                $q->where('is_active', true);
                if ($currentSchool) {
                    $q->orWhere('id', $currentSchool);
                }
            })],
            'referrer_name' => ['nullable', 'string', 'max:100'],
            'referrer_class_name' => ['nullable', 'string', 'max:50'],
            'referrer_class_option' => ['nullable', 'string', Rule::in([...ReferrerClass::OPTIONS, ReferrerClass::OTHER])],
            'referrer_class_other' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->hasAny(['province_code', 'regency_code', 'district_code', 'village_code'])) {
                return;
            }

            $codes = ['province_code', 'regency_code', 'district_code', 'village_code'];
            $provided = array_filter($codes, fn ($code) => $this->filled($code));
            if ($provided === []) {
                return;
            }
            if (count($provided) !== count($codes)) {
                $v->errors()->add('village_code', 'Lengkapi alamat sampai desa/kelurahan atau kosongkan seluruh alamat.');

                return;
            }

            $village = (string) $this->input('village_code');
            $district = Region::parentOf($village);
            $regency = $district ? Region::parentOf($district) : null;
            $province = $regency ? Region::parentOf($regency) : null;

            if ($this->input('district_code') !== $district
                || $this->input('regency_code') !== $regency
                || $this->input('province_code') !== $province) {
                $v->errors()->add('village_code', 'Pilihan alamat tidak konsisten. Pilih ulang dari provinsi.');

                return;
            }

            if (Region::whereIn('code', [$province, $regency, $district, $village])->count() !== 4) {
                $v->errors()->add('village_code', 'Data wilayah belum lengkap. Hubungi admin.');
            }

        });
    }

    public function attributes(): array
    {
        return [
            'registration_number' => 'nomor pendaftaran',
            'name'                => 'nama pendaftar',
            'gender'              => 'jenis kelamin',
            'birth_place'         => 'tempat lahir',
            'birth_date'          => 'tanggal lahir',
            'province_code'       => 'provinsi',
            'regency_code'        => 'kabupaten/kota',
            'district_code'       => 'kecamatan',
            'village_code'        => 'desa/kelurahan',
            'rt'                  => 'RT',
            'rw'                  => 'RW',
            'father_name'         => 'nama ayah',
            'mother_name'         => 'nama ibu',
            'phone'               => 'nomor HP',
            'student_phone'       => 'nomor HP siswa',
            'school_id'           => 'asal sekolah',
            'referrer_name'       => 'nama pembawa',
            'referrer_class_name' => 'kelas pembawa',
            'referrer_class_option' => 'kelas pembawa',
            'referrer_class_other' => 'kelas pembawa lainnya',
        ];
    }

    public function messages(): array
    {
        return [
            'registration_number.regex'  => 'Format nomor harus A- diikuti angka, misalnya A-001 (minimal 3 angka).',
            'village_code.required'      => 'Pilih alamat sampai desa/kelurahan.',
            'village_code.exists'        => 'Pilih alamat sampai desa/kelurahan.',
            'birth_date.date_format'     => 'Tanggal lahir harus berformat dd/mm/yyyy, misalnya 25/12/2010.',
            'birth_date.before_or_equal' => 'Tanggal lahir tidak boleh melewati hari ini.',
            'birth_date.after'           => 'Tanggal lahir tidak wajar.',
            'phone.regex'                => 'Nomor HP hanya berisi angka, diawali 08, panjang 10 sampai 13 digit.',
            'student_phone.regex'       => 'Nomor HP siswa hanya berisi angka, diawali 08, panjang 10 sampai 13 digit.',
            'school_id.exists'           => 'Pilih asal sekolah dari daftar. Bila belum ada, minta admin menambahkannya.',
            'rt.regex'                   => 'RT berupa angka 1 sampai 3 digit.',
            'rw.regex'                   => 'RW berupa angka 1 sampai 3 digit.',
        ];
    }

    /** Kolom database siap simpan (nama wilayah disalin sebagai teks, RT/RW dibuat 3 digit). */
    public function payload(): array
    {
        $v = $this->validated();
        $codes = array_filter([
            $v['province_code'] ?? null,
            $v['regency_code'] ?? null,
            $v['district_code'] ?? null,
            $v['village_code'] ?? null,
        ]);
        $names = Region::whereIn('code', $codes)
            ->pluck('name', 'code');

        return [
            'registration_number' => $v['registration_number'],
            'registration_seq'    => Applicant::parseNumber($v['registration_number']),
            'name'                => $v['name'],
            'gender'              => $v['gender'],
            'birth_place'         => $v['birth_place'] ?? null,
            'birth_date'          => $v['birth_date'] ?? null,
            'province_code'       => $v['province_code'] ?? null,
            'province_name'       => $names[$v['province_code'] ?? ''] ?? null,
            'regency_code'        => $v['regency_code'] ?? null,
            'regency_name'        => $names[$v['regency_code'] ?? ''] ?? null,
            'district_code'       => $v['district_code'] ?? null,
            'district_name'       => $names[$v['district_code'] ?? ''] ?? null,
            'village_code'        => $v['village_code'] ?? null,
            'village_name'        => $names[$v['village_code'] ?? ''] ?? null,
            'rt'                  => isset($v['rt']) ? str_pad($v['rt'], 3, '0', STR_PAD_LEFT) : null,
            'rw'                  => isset($v['rw']) ? str_pad($v['rw'], 3, '0', STR_PAD_LEFT) : null,
            'father_name'         => $v['father_name'] ?? null,
            'mother_name'         => $v['mother_name'] ?? null,
            'phone'               => $v['phone'] ?? null,
            'student_phone'       => $v['student_phone'] ?? null,
            'school_id'           => (int) $v['school_id'],
            'referrer_name'       => isset($v['referrer_name']) ? trim(preg_replace('/\s+/u', ' ', $v['referrer_name'])) ?: null : null,
            'referrer_class_name' => $this->referrerClassName($v),
            'person_key'          => Applicant::personKey($v['name'], $v['birth_date'] ?? null, $v['mother_name'] ?? null),
        ];
    }

    private function referrerClassName(array $validated): ?string
    {
        if (array_key_exists('referrer_class_option', $validated)) {
            $className = $validated['referrer_class_option'] === ReferrerClass::OTHER
                ? ($validated['referrer_class_other'] ?? null)
                : ($validated['referrer_class_option'] ?? null);
        } else {
            $className = $validated['referrer_class_name'] ?? null;
        }

        return $className !== null ? (trim(preg_replace('/\s+/u', ' ', $className)) ?: null) : null;
    }
}
