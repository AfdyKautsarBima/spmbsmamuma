<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->string('birth_place', 100)->nullable()->change();
            $table->date('birth_date')->nullable()->change();
            $table->string('province_code', 13)->nullable()->change();
            $table->string('province_name')->nullable()->change();
            $table->string('regency_code', 13)->nullable()->change();
            $table->string('regency_name')->nullable()->change();
            $table->string('district_code', 13)->nullable()->change();
            $table->string('district_name')->nullable()->change();
            $table->string('village_code', 13)->nullable()->change();
            $table->string('village_name')->nullable()->change();
            $table->string('rt', 3)->nullable()->change();
            $table->string('rw', 3)->nullable()->change();
            $table->string('father_name', 100)->nullable()->change();
            $table->string('mother_name', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->string('birth_place', 100)->nullable(false)->change();
            $table->date('birth_date')->nullable(false)->change();
            $table->string('province_code', 13)->nullable(false)->change();
            $table->string('province_name')->nullable(false)->change();
            $table->string('regency_code', 13)->nullable(false)->change();
            $table->string('regency_name')->nullable(false)->change();
            $table->string('district_code', 13)->nullable(false)->change();
            $table->string('district_name')->nullable(false)->change();
            $table->string('village_code', 13)->nullable(false)->change();
            $table->string('village_name')->nullable(false)->change();
            $table->string('rt', 3)->nullable(false)->change();
            $table->string('rw', 3)->nullable(false)->change();
            $table->string('father_name', 100)->nullable(false)->change();
            $table->string('mother_name', 100)->nullable(false)->change();
        });
    }
};
