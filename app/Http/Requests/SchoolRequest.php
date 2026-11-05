<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // dibatasi middleware role:admin di route
    }

    protected function prepareForValidation(): void
    {
        $npsn = trim((string) $this->input('npsn'));

        $this->merge([
            'name' => trim(preg_replace('/\s+/u', ' ', (string) $this->input('name'))),
            'npsn' => $npsn === '' ? null : $npsn,
        ]);
    }

    public function rules(): array
    {
        $school = $this->route('school');

        return [
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('schools', 'name')->where('regency_code', $this->input('regency_code'))->ignore($school),
            ],
            'npsn'         => ['nullable', 'regex:/\A[A-Za-z0-9]{8}\z/', Rule::unique('schools', 'npsn')->ignore($school)],
            'regency_code' => ['required', Rule::exists('regions', 'code')->where('level', 2)],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nama sekolah', 'npsn' => 'NPSN', 'regency_code' => 'kabupaten/kota asal'];
    }

    public function messages(): array
    {
        return [
            'name.unique'        => 'Sekolah dengan nama yang sama sudah ada di kabupaten/kota tersebut.',
            'regency_code.exists' => 'Pilih kabupaten/kota dari daftar. Pastikan data wilayah sudah diimpor.',
        ];
    }
}
