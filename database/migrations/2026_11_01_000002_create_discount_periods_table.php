<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained();
            $table->string('name', 100);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedTinyInteger('percent');           // 1 sampai 99
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['academic_year_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_periods');
    }
};
