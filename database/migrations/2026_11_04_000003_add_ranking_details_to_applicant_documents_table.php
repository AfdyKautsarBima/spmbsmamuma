<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicant_documents', function (Blueprint $table) {
            $table->string('grade_level', 2)->nullable();
            $table->string('parallel_class', 2)->nullable();
            $table->unsignedInteger('rank_position')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('applicant_documents', function (Blueprint $table) {
            $table->dropColumn(['grade_level', 'parallel_class', 'rank_position']);
        });
    }
};
