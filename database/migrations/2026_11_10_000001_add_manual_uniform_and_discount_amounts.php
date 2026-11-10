<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_years', function (Blueprint $table) {
            $table->unsignedBigInteger('uniform_threshold_amount')->default(0);
        });

        DB::table('academic_years')->orderBy('id')->get()->each(function ($year) {
            DB::table('academic_years')->where('id', $year->id)->update([
                'uniform_threshold_amount' => intdiv(
                    (int) $year->registration_fee * (int) $year->uniform_threshold_percent + 99,
                    100,
                ),
            ]);
        });

        Schema::table('discount_periods', function (Blueprint $table) {
            $table->unsignedBigInteger('payable_amount')->nullable();
            $table->unsignedBigInteger('uniform_threshold_amount')->nullable();
        });

        DB::table('discount_periods')->update(['is_active' => false]);

        Schema::table('applicant_billings', function (Blueprint $table) {
            $table->unsignedBigInteger('uniform_threshold_amount')->nullable();
        });

        DB::table('applicant_billings as b')
            ->leftJoin('uniform_entitlements as e', 'e.applicant_id', '=', 'b.applicant_id')
            ->join('academic_years as y', 'y.id', '=', 'b.academic_year_id')
            ->select('b.id', 'b.net_bill', 'y.uniform_threshold_percent', 'e.threshold_amount')
            ->orderBy('b.id')
            ->get()
            ->each(function ($billing) {
                $threshold = $billing->threshold_amount ?? intdiv(
                    (int) $billing->net_bill * (int) $billing->uniform_threshold_percent + 99,
                    100,
                );

                DB::table('applicant_billings')->where('id', $billing->id)
                    ->update(['uniform_threshold_amount' => $threshold]);
            });
    }

    public function down(): void
    {
        Schema::table('applicant_billings', function (Blueprint $table) {
            $table->dropColumn('uniform_threshold_amount');
        });

        Schema::table('discount_periods', function (Blueprint $table) {
            $table->dropColumn(['payable_amount', 'uniform_threshold_amount']);
        });

        Schema::table('academic_years', function (Blueprint $table) {
            $table->dropColumn('uniform_threshold_amount');
        });
    }
};
