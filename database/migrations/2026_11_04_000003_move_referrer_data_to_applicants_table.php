<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->string('referrer_name', 100)->nullable()->after('school_id');
            $table->string('referrer_class_name', 50)->nullable()->after('referrer_name');
        });

        DB::table('applicants')
            ->whereNotNull('referrer_id')
            ->update([
                'referrer_name' => DB::raw('(select name from referrers where referrers.id = applicants.referrer_id)'),
                'referrer_class_name' => DB::raw('(select class_name from referrers where referrers.id = applicants.referrer_id)'),
            ]);

        Schema::table('applicants', function (Blueprint $table) {
            $table->dropForeign(['referrer_id']);
            $table->dropColumn('referrer_id');
        });
        Schema::dropIfExists('referrers');
    }

    public function down(): void
    {
        Schema::create('referrers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('name_key', 100)->unique();
            $table->string('class_name', 50)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::table('applicants', function (Blueprint $table) {
            $table->foreignId('referrer_id')->nullable()->after('school_id')->constrained('referrers');
        });

        $referrers = [];
        $usedKeys = [];
        DB::table('applicants')
            ->select('referrer_name', 'referrer_class_name')
            ->whereNotNull('referrer_name')
            ->distinct()
            ->orderBy('referrer_name')
            ->orderBy('referrer_class_name')
            ->get()
            ->each(function ($row) use (&$referrers, &$usedKeys) {
                $baseKey = trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($row->referrer_name)));
                $key = mb_substr($baseKey, 0, 100);
                if (isset($usedKeys[$key])) {
                    $key = mb_substr($baseKey, 0, 80).'-'.substr(hash('sha256', (string) $row->referrer_class_name), 0, 16);
                }
                $usedKeys[$key] = true;

                $id = DB::table('referrers')->insertGetId([
                    'name' => $row->referrer_name,
                    'name_key' => $key,
                    'class_name' => $row->referrer_class_name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $referrers[json_encode([$row->referrer_name, $row->referrer_class_name], JSON_THROW_ON_ERROR)] = $id;
            });

        DB::table('applicants')
            ->select('id', 'referrer_name', 'referrer_class_name')
            ->whereNotNull('referrer_name')
            ->orderBy('id')
            ->chunkById(500, function ($applicants) use ($referrers) {
                foreach ($applicants as $applicant) {
                    $key = json_encode([$applicant->referrer_name, $applicant->referrer_class_name], JSON_THROW_ON_ERROR);
                    DB::table('applicants')->where('id', $applicant->id)->update(['referrer_id' => $referrers[$key]]);
                }
            });

        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn(['referrer_name', 'referrer_class_name']);
        });
    }
};
