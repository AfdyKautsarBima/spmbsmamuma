<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained();
            $table->foreignId('academic_year_id')->constrained();
            $table->unsignedInteger('receipt_seq');
            $table->string('receipt_number', 20)->index();
            $table->unsignedBigInteger('amount');                      // rupiah
            $table->timestamp('paid_at');
            $table->foreignId('received_by')->constrained('users');
            $table->string('note')->nullable();
            $table->timestamp('cancelled_at')->nullable()->index();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->unique(['academic_year_id', 'receipt_seq']);
            $table->index(['applicant_id', 'cancelled_at']);
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
