<?php

namespace Database\Seeders;

use App\Models\UniformSize;
use Illuminate\Database\Seeder;

class UniformSizeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['S', 'M', 'L', 'XL', 'XXL', 'XXXL'] as $i => $name) {
            UniformSize::firstOrCreate(['name' => $name], ['sort_order' => ($i + 1) * 10]);
        }
    }
}
