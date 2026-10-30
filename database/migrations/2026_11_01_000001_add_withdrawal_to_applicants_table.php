<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->date('withdrawn_at')->nullable()->index();
            $table->string('withdrawn_reason')->nullable();
            $table->foreignId('withdrawn_by')->nullable()->constrained('users');
            $table->text('refund_note')->nullable();   // hanya catatan, pengembalian dana diurus di luar sistem
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropForeign(['withdrawn_by']);
            $table->dropIndex(['withdrawn_at']);
            $table->dropColumn(['withdrawn_at', 'withdrawn_reason', 'withdrawn_by', 'refund_note']);
        });
    }
};
