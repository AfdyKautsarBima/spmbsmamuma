<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name'      => 'Administrator',
                'password'  => env('SEED_ADMIN_PASSWORD', 'ganti-password-ini'),
                'role'      => Role::Admin,
                'is_active' => true,
            ],
        );

        if (! AcademicYear::exists()) {
            AcademicYear::create([
                'name'                      => '2026/2027',
                'registration_fee'          => 0,
                'uniform_threshold_percent' => 0,
                'is_active'                 => true,
            ]);
        }

        $this->call(OccupationSeeder::class);
        $this->call(UniformSizeSeeder::class);
    }
}
