<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('name_key', 100)->unique();       // nama yang sudah dinormalkan, untuk deteksi duplikat
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrers');
    }
};
