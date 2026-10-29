<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OccupationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // dibatasi middleware role:admin di route
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim(preg_replace('/\s+/u', ' ', (string) $this->input('name')))]);
    }

    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:100', Rule::unique('occupations', 'name')->ignore($this->route('occupation'))],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nama pekerjaan', 'sort_order' => 'urutan'];
    }
}
