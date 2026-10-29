<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicant_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('status', 12)->default('belum');
            $table->string('note')->nullable();                                  // keterangan (sertifikat kejuaraan)
            $table->timestamp('handled_at')->nullable();                         // terakhir status diubah
            $table->foreignId('handled_by')->nullable()->constrained('users');   // oleh siapa
            $table->timestamps();

            $table->unique(['applicant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_documents');
    }
};
