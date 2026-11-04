<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicant_documents', function (Blueprint $table) {
            $table->string('card_number', 50)->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('applicant_documents', function (Blueprint $table) {
            $table->dropColumn('card_number');
        });
    }
};
