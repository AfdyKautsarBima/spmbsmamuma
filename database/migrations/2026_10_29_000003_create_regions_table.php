<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->string('code', 13)->primary();          // 33 | 33.19 | 33.19.01 | 33.19.01.2001
            $table->string('name');
            $table->unsignedTinyInteger('level');            // 1 provinsi, 2 kab/kota, 3 kecamatan, 4 desa/kel
            $table->string('parent_code', 13)->nullable();
            $table->index(['parent_code', 'name']);
            $table->index('level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
