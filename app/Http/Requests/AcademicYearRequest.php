<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // dibatasi middleware role:admin di route
    }

    public function rules(): array
    {
        $year = $this->route('academic_year');

        return [
            'name' => [
                'required', 'regex:/^\d{4}\/\d{4}$/',
                Rule::unique('academic_years', 'name')->ignore($year),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (preg_match('/^(\d{4})\/(\d{4})$/', (string) $value, $m) && (int) $m[2] !== (int) $m[1] + 1) {
                        $fail('Tahun akhir harus satu tahun setelah tahun awal, misalnya 2026/2027.');
                    }
                },
            ],
            'registration_fee'          => ['required', 'integer', 'min:0', 'max:1000000000'],
            'uniform_threshold_percent' => ['required', 'integer', 'between:0,100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name'                      => 'tahun ajaran',
            'registration_fee'          => 'biaya daftar ulang',
            'uniform_threshold_percent' => 'batas seragam',
        ];
    }

    public function messages(): array
    {
        return ['name.regex' => 'Format tahun ajaran harus seperti 2026/2027.'];
    }
}
