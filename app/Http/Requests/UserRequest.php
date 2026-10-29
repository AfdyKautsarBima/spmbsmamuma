<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // dibatasi middleware role:admin di route
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('username')) {
            $this->merge(['username' => Str::lower(trim((string) $this->input('username')))]);
        }
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name'     => ['required', 'string', 'max:100'],
            'username' => [
                'required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($user),
            ],
            'role'     => ['required', Rule::enum(Role::class)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nama', 'username' => 'username', 'role' => 'peran', 'password' => 'password'];
    }

    public function messages(): array
    {
        return ['username.regex' => 'Username hanya boleh berisi huruf kecil, angka, titik, garis bawah, dan tanda hubung.'];
    }
}
