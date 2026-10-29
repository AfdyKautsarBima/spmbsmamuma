<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained();

            // Nomor manual "A-001". Angka urut disimpan terpisah agar pengurutan benar (A-1000 setelah A-999).
            $table->string('registration_number', 12)->index();
            $table->unsignedInteger('registration_seq');

            $table->string('name', 100);
            $table->char('gender', 1);                       // L | P
            $table->string('birth_place', 100);
            $table->date('birth_date');

            // Alamat: kode dan salinan nama (data lama tidak berubah bila master wilayah diperbarui)
            $table->string('province_code', 13);
            $table->string('province_name');
            $table->string('regency_code', 13);
            $table->string('regency_name');
            $table->string('district_code', 13);
            $table->string('district_name');
            $table->string('village_code', 13);
            $table->string('village_name');
            $table->string('rt', 3);
            $table->string('rw', 3);

            $table->string('father_name', 100);
            $table->string('mother_name', 100);
            $table->string('phone', 20)->nullable();

            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('referrer_id')->nullable()->constrained('referrers');

            $table->string('person_key')->index();           // nama|tgl lahir|nama ibu (dinormalkan) untuk peringatan data ganda
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['academic_year_id', 'registration_seq']);
            $table->index(['academic_year_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicants');
    }
};
