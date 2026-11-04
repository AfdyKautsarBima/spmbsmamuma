<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use App\Models\Applicant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentRequest extends FormRequest
{
    private const DIGIT_FIELDS = ['number', 'student_nik', 'father_nik', 'mother_nik', 'guardian_nik', 'card_number'];

    public function authorize(): bool
    {
        /** @var Applicant $applicant */
        $applicant = $this->route('applicant');
        abort_unless($applicant->academicYear()->value('is_active'), 403, 'Data tahun ajaran arsip hanya bisa dilihat.');

        return true; // peran dibatasi middleware di route
    }

    protected function prepareForValidation(): void
    {
        $family = (array) $this->input('family', []);
        $documents = (array) $this->input('docs', []);

        foreach (self::DIGIT_FIELDS as $key) {
            if (isset($family[$key])) {
                $family[$key] = preg_replace('/\D/', '', (string) $family[$key]);
            }
        }
        foreach (['kks', 'pkh'] as $type) {
            if (isset($documents[$type]['card_number'])) {
                $documents[$type]['card_number'] = preg_replace('/\D/', '', (string) $documents[$type]['card_number']);
            }
        }
        foreach (['father_name', 'mother_name', 'guardian_name', 'guardian_relation'] as $key) {
            if (isset($family[$key])) {
                $family[$key] = trim(preg_replace('/\s+/u', ' ', (string) $family[$key]));
            }
        }
        if (isset($family['guardian_phone'])) {
            $family['guardian_phone'] = preg_replace('/[\s\-.]/', '', (string) $family['guardian_phone']);
        }

        $this->merge([
            'family' => $family,
            'docs'   => $documents,
            'nisn'   => preg_replace('/\D/', '', (string) $this->input('nisn')),
        ]);
    }

    public function rules(): array
    {
        /** @var Applicant $applicant */
        $applicant = $this->route('applicant');
        $rules = [];

        foreach (DocumentType::cases() as $type) {
            $allowed = array_map(fn ($s) => $s->value, $type->allowedStatuses());
            $rules["docs.{$type->value}.status"] = ['required', Rule::in($allowed)];
        }
        $rules['docs.sertifikat.note'] = ['nullable', 'string', 'max:150'];

        foreach (['kks', 'pkh'] as $type) {
            $rules["docs.{$type}.card_number"] = [
                Rule::requiredIf($this->input("docs.{$type}.status") === 'diterima'),
                'nullable',
                'digits_between:1,50',
            ];
        }
        $rules['docs.surat_peringkat.ranking_type'] = [
            Rule::requiredIf($this->input('docs.surat_peringkat.status') === 'diterima'),
            'nullable',
            Rule::in(['kelas', 'paralel']),
        ];
        $rules['docs.surat_peringkat.rank'] = [
            Rule::requiredIf($this->input('docs.surat_peringkat.status') === 'diterima'),
            'nullable',
            'integer',
            'min:1',
            'max:2147483647',
        ];

        if ($this->input('docs.kk.status') === 'diterima') {
            $rules = array_merge($rules, $this->familyRules($applicant));
        }

        if ($this->input('docs.skl.status') === 'diterima') {
            $rules['nisn'] = [
                'required', 'digits:10',
                function (string $attribute, mixed $value, \Closure $fail) use ($applicant) {
                    $other = Applicant::withTrashed()
                        ->where('academic_year_id', $applicant->academic_year_id)
                        ->where('nisn', $value)
                        ->where('id', '!=', $applicant->id)
                        ->first();

                    if ($other) {
                        $fail("NISN sudah dipakai oleh {$other->registration_number} {$other->name}".($other->trashed() ? ' (data dihapus)' : '').'.');
                    }
                },
            ];
        }

        return $rules;
    }

    private function familyRules(Applicant $applicant): array
    {
        $detail = $applicant->familyDetail;
        $currentOccupations = array_filter([$detail?->father_occupation_id, $detail?->mother_occupation_id, $detail?->guardian_occupation_id]);
        $occupation = Rule::exists('occupations', 'id')->where(function ($q) use ($currentOccupations) {
            $q->where('is_active', true);
            if ($currentOccupations) {
                $q->orWhereIn('id', $currentOccupations);
            }
        });

        $studentYear = $applicant->birth_date->year;
        $minYear = 1920;
        $maxYear = $studentYear - 12;

        $rules = [
            'family.number' => ['required', 'digits:16'],
            'family.student_nik' => [
                'required', 'digits:16',
                function (string $attribute, mixed $value, \Closure $fail) use ($applicant) {
                    $other = \App\Models\ApplicantFamilyDetail::query()
                        ->where('academic_year_id', $applicant->academic_year_id)
                        ->where('student_nik', $value)
                        ->where('applicant_id', '!=', $applicant->id)
                        ->with('applicant')
                        ->first();

                    if ($other) {
                        $fail("NIK murid sudah dipakai oleh {$other->applicant->registration_number} {$other->applicant->name}".($other->applicant->trashed() ? ' (data dihapus)' : '').'.');
                    }
                },
            ],
            'family.father_name' => ['required', 'string', 'max:100'],
            'family.mother_name' => ['required', 'string', 'max:100'],
            'family.father_absent' => ['nullable', 'boolean'],
            'family.mother_absent' => ['nullable', 'boolean'],
        ];

        foreach (['father', 'mother'] as $parent) {
            if (! $this->boolean("family.{$parent}_absent")) {
                $rules["family.{$parent}_nik"] = ['required', 'digits:16'];
                $rules["family.{$parent}_birth_year"] = ['required', 'integer', "between:{$minYear},{$maxYear}"];
                $rules["family.{$parent}_occupation_id"] = ['required', $occupation];
            }
        }

        $absent = (int) $this->boolean('family.father_absent') + (int) $this->boolean('family.mother_absent');
        $guardianFilled = collect(['guardian_name', 'guardian_nik', 'guardian_relation', 'guardian_occupation_id', 'guardian_phone'])
            ->contains(fn ($k) => filled($this->input("family.{$k}")));

        if ($absent >= 1 && ($absent === 2 || $guardianFilled)) {
            $rules['family.guardian_name'] = ['required', 'string', 'max:100'];
            $rules['family.guardian_nik'] = ['required', 'digits:16'];
            $rules['family.guardian_relation'] = ['required', 'string', 'max:50'];
            $rules['family.guardian_occupation_id'] = ['required', $occupation];
            $rules['family.guardian_phone'] = ['nullable', 'regex:/^08\d{8,11}$/'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        $attributes = [
            'docs.sertifikat.note'           => 'keterangan sertifikat',
            'nisn'                           => 'NISN',
            'docs.kks.card_number'           => 'nomor kartu KKS/PKH',
            'docs.pkh.card_number'           => 'nomor kartu KIP',
            'docs.surat_peringkat.ranking_type' => 'jenis peringkat',
            'docs.surat_peringkat.rank'      => 'peringkat',
            'family.number'                  => 'nomor KK',
            'family.student_nik'             => 'NIK murid',
            'family.father_name'             => 'nama ayah',
            'family.mother_name'             => 'nama ibu',
            'family.father_nik'              => 'NIK ayah',
            'family.mother_nik'              => 'NIK ibu',
            'family.father_birth_year'       => 'tahun lahir ayah',
            'family.mother_birth_year'       => 'tahun lahir ibu',
            'family.father_occupation_id'    => 'pekerjaan ayah',
            'family.mother_occupation_id'    => 'pekerjaan ibu',
            'family.guardian_name'           => 'nama wali',
            'family.guardian_nik'            => 'NIK wali',
            'family.guardian_relation'       => 'hubungan wali',
            'family.guardian_occupation_id'  => 'pekerjaan wali',
            'family.guardian_phone'          => 'nomor HP wali',
        ];

        foreach (DocumentType::cases() as $type) {
            $attributes["docs.{$type->value}.status"] = 'status '.$type->label();
        }

        return $attributes;
    }

    public function messages(): array
    {
        return [
            'family.father_birth_year.between' => 'Tahun lahir ayah tidak wajar (minimal 1920 dan sedikitnya 12 tahun lebih tua dari murid).',
            'family.mother_birth_year.between' => 'Tahun lahir ibu tidak wajar (minimal 1920 dan sedikitnya 12 tahun lebih tua dari murid).',
            'family.guardian_phone.regex'      => 'Nomor HP wali hanya berisi angka, diawali 08, panjang 10 sampai 13 digit.',
            'digits'                           => ':attribute harus :digits digit angka.',
            'digits_between'                   => ':attribute harus berupa angka dengan panjang :min sampai :max digit.',
            'docs.surat_peringkat.rank.integer' => ':attribute harus berupa angka.',
            'docs.surat_peringkat.rank.min'     => ':attribute harus angka positif.',
        ];
    }
}
