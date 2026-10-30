<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tagihan yang "dibekukan" per murid: perubahan biaya atau periode diskon sesudahnya tidak memengaruhi murid ini.
        Schema::create('applicant_billings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->unique()->constrained();
            $table->foreignId('academic_year_id')->constrained();
            $table->unsignedBigInteger('fee_snapshot');                // biaya tahun ajaran saat dibekukan
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->string('discount_label')->nullable();
            $table->string('discount_type', 10)->default('none');       // none | periode | manual
            $table->foreignId('discount_period_id')->nullable()->constrained('discount_periods');
            $table->unsignedBigInteger('net_bill');                    // tagihan bersih
            $table->string('reason')->nullable();                      // alasan diskon manual
            $table->foreignId('set_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_billings');
    }
};
