<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicant_family_details', function (Blueprint $table) {
            $table->unsignedSmallInteger('guardian_birth_year')->nullable()->after('guardian_nik');
        });
    }

    public function down(): void
    {
        Schema::table('applicant_family_details', function (Blueprint $table) {
            $table->dropColumn('guardian_birth_year');
        });
    }
};
