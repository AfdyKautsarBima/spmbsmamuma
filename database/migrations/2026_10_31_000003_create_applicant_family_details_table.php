<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicant_family_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();

            $table->string('kk_number', 16)->index();       // boleh sama (saudara kandung)
            $table->string('student_nik', 16);

            $table->boolean('father_absent')->default(false);   // tidak ada / almarhum
            $table->string('father_nik', 16)->nullable();
            $table->unsignedSmallInteger('father_birth_year')->nullable();
            $table->foreignId('father_occupation_id')->nullable()->constrained('occupations');

            $table->boolean('mother_absent')->default(false);
            $table->string('mother_nik', 16)->nullable();
            $table->unsignedSmallInteger('mother_birth_year')->nullable();
            $table->foreignId('mother_occupation_id')->nullable()->constrained('occupations');

            $table->string('guardian_name', 100)->nullable();
            $table->string('guardian_nik', 16)->nullable();
            $table->string('guardian_relation', 50)->nullable();
            $table->foreignId('guardian_occupation_id')->nullable()->constrained('occupations');
            $table->string('guardian_phone', 20)->nullable();

            $table->timestamps();

            $table->unique(['academic_year_id', 'student_nik']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_family_details');
    }
};
