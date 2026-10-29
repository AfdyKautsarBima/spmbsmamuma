<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('npsn', 8)->nullable()->unique();
            $table->string('regency_code', 13)->index();     // kabupaten/kota asal (kode wilayah)
            $table->string('regency_name');                  // salinan teks, tidak ikut berubah bila master wilayah diperbarui
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['name', 'regency_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
