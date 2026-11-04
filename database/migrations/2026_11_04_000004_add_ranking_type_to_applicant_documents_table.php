<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicant_documents', function (Blueprint $table) {
            $table->string('ranking_type', 10)->nullable();
        });

        DB::table('applicant_documents')
            ->whereNotNull('grade_level')
            ->update(['ranking_type' => 'kelas']);

        DB::table('applicant_documents')
            ->whereNull('grade_level')
            ->whereNotNull('parallel_class')
            ->update(['ranking_type' => 'paralel']);
    }

    public function down(): void
    {
        Schema::table('applicant_documents', function (Blueprint $table) {
            $table->dropColumn('ranking_type');
        });
    }
};
