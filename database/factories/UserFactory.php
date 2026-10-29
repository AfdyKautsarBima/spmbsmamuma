<?php

namespace Database\Factories;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'           => fake()->name(),
            'username'       => strtolower(fake()->unique()->userName()),
            'password'       => 'password',
            'role'           => Role::Pendaftaran,
            'is_active'      => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => Role::Admin]);
    }

    public function role(Role $role): static
    {
        return $this->state(['role' => $role]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
