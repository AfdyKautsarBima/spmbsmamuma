<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->string('nisn', 10)->nullable();                         // diisi saat FC SKL diterima
            $table->boolean('documents_complete')->default(false)->index(); // dihitung ulang setiap cek berkas disimpan

            $table->unique(['academic_year_id', 'nisn']);
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropUnique(['academic_year_id', 'nisn']);
            $table->dropIndex(['documents_complete']);
            $table->dropColumn(['nisn', 'documents_complete']);
        });
    }
};
