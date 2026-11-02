<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jenis seragam per tahun ajaran (semua murid yang berhak mendapat semua jenis yang aktif).
        Schema::create('uniform_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained();
            $table->string('name', 100);
            $table->boolean('needs_size')->default(false);   // hanya seragam olahraga yang perlu ukuran
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['academic_year_id', 'name']);
        });

        // Daftar ukuran (S, M, L, ...) dipakai bersama semua tahun.
        Schema::create('uniform_sizes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20)->unique();
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Hak seragam. Disimpan sekali saat murid mencapai ambang, lengkap dengan ambang yang berlaku saat itu.
        Schema::create('uniform_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->unique()->constrained();
            $table->foreignId('academic_year_id')->constrained();
            $table->unsignedTinyInteger('threshold_percent');
            $table->unsignedBigInteger('threshold_amount');   // rupiah, dibulatkan ke atas
            $table->unsignedBigInteger('net_bill');           // tagihan bersih saat hak muncul
            $table->timestamp('granted_at');
            $table->timestamps();
        });

        Schema::create('applicant_uniform_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained();
            $table->foreignId('uniform_type_id')->constrained();
            $table->foreignId('uniform_size_id')->constrained();
            $table->foreignId('set_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['applicant_id', 'uniform_type_id']);
        });

        Schema::create('uniform_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained();
            $table->foreignId('uniform_type_id')->constrained();
            $table->foreignId('uniform_size_id')->nullable()->constrained();   // ukuran saat diserahkan (bila perlu ukuran)
            $table->timestamp('handed_at');
            $table->foreignId('handed_by')->constrained('users');
            $table->timestamps();

            $table->unique(['applicant_id', 'uniform_type_id']);
            $table->index('handed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uniform_handovers');
        Schema::dropIfExists('applicant_uniform_sizes');
        Schema::dropIfExists('uniform_entitlements');
        Schema::dropIfExists('uniform_sizes');
        Schema::dropIfExists('uniform_types');
    }
};
