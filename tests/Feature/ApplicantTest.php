<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\Referrer;
use App\Models\Region;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicantTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year;
    private School $school;
    private Referrer $referrer;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = AcademicYear::create(['name' => '2026/2027', 'registration_fee' => 0, 'uniform_threshold_percent' => 0, 'is_active' => true]);

        foreach ([
            ['33', 'JAWA TENGAH', 1, null],
            ['33.19', 'KAB. KUDUS', 2, '33'],
            ['33.20', 'KAB. JEPARA', 2, '33'],
            ['33.19.01', 'KALIWUNGU', 3, '33.19'],
            ['33.19.01.2001', 'BAKALAN KRAPYAK', 4, '33.19.01'],
        ] as [$code, $name, $level, $parent]) {
            Region::create(['code' => $code, 'name' => $name, 'level' => $level, 'parent_code' => $parent]);
        }

        $this->school = School::create(['name' => 'SMP Negeri 1 Kudus', 'regency_code' => '33.19', 'regency_name' => 'KAB. KUDUS']);
        $this->referrer = Referrer::create(['name' => 'Budi Santoso', 'name_key' => 'budi santoso']);
        $this->user = User::factory()->create(); // panitia pendaftaran
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'registration_number' => 'A-001',
            'name'                => 'Ahmad Fauzi',
            'gender'              => 'L',
            'birth_place'         => 'Kudus',
            'birth_date'          => '2010-05-01',
            'province_code'       => '33',
            'regency_code'        => '33.19',
            'district_code'       => '33.19.01',
            'village_code'        => '33.19.01.2001',
            'rt'                  => '3',
            'rw'                  => '2',
            'father_name'         => 'Slamet',
            'mother_name'         => 'Siti Aminah',
            'phone'               => '081234567890',
            'school_id'           => $this->school->id,
            'referrer_id'         => $this->referrer->id,
        ], $override);
    }

    private function makeApplicant(int $seq, array $override = []): Applicant
    {
        return Applicant::create(array_merge([
            'academic_year_id' => $this->year->id,
            'registration_number' => Applicant::formatNumber($seq),
            'registration_seq' => $seq,
            'name' => 'Murid '.$seq, 'gender' => 'P', 'birth_place' => 'Kudus', 'birth_date' => '2010-01-01',
            'province_code' => '33', 'province_name' => 'JAWA TENGAH',
            'regency_code' => '33.19', 'regency_name' => 'KAB. KUDUS',
            'district_code' => '33.19.01', 'district_name' => 'KALIWUNGU',
            'village_code' => '33.19.01.2001', 'village_name' => 'BAKALAN KRAPYAK',
            'rt' => '001', 'rw' => '001', 'father_name' => 'Ayah '.$seq, 'mother_name' => 'Ibu '.$seq,
            'school_id' => $this->school->id,
            'person_key' => Applicant::personKey('Murid '.$seq, '2010-01-01', 'Ibu '.$seq),
            'created_by' => $this->user->id,
        ], $override));
    }

    // ---------- nomor ----------

    public function test_format_and_parse_number(): void
    {
        $this->assertSame('A-001', Applicant::formatNumber(1));
        $this->assertSame('A-999', Applicant::formatNumber(999));
        $this->assertSame('A-1000', Applicant::formatNumber(1000));
        $this->assertSame(1000, Applicant::parseNumber('A-1000'));
        $this->assertNull(Applicant::parseNumber('A-01'));
        $this->assertNull(Applicant::parseNumber('A-0001'));
        $this->assertNull(Applicant::parseNumber('A-000'));
        $this->assertNull(Applicant::parseNumber('B-001'));
    }

    public function test_store_creates_applicant_with_copied_names_and_padded_rt_rw(): void
    {
        $response = $this->actingAs($this->user)->post('/applicants', $this->payload());

        $applicant = Applicant::firstOrFail();
        $response->assertRedirect(route('applicants.create'))->assertSessionHas('saved');

        $this->assertSame('A-001', $applicant->registration_number);
        $this->assertSame(1, $applicant->registration_seq);
        $this->assertSame($this->year->id, $applicant->academic_year_id);
        $this->assertSame($this->user->id, $applicant->created_by);
        $this->assertSame('003', $applicant->rt);
        $this->assertSame('002', $applicant->rw);
        $this->assertSame('JAWA TENGAH', $applicant->province_name);
        $this->assertSame('BAKALAN KRAPYAK', $applicant->village_name);
        $this->assertDatabaseHas('activity_logs', ['event' => 'applicant.created', 'subject_id' => $applicant->id]);
    }

    public function test_number_is_normalized_and_validated(): void
    {
        $this->actingAs($this->user)->post('/applicants', $this->payload(['registration_number' => ' a001 ']))->assertSessionHasNoErrors();
        $this->assertSame('A-001', Applicant::firstOrFail()->registration_number);

        foreach (['B-002', 'A-02', 'A-0002', 'A-000', 'abc'] as $bad) {
            $this->actingAs($this->user)->post('/applicants', $this->payload(['registration_number' => $bad, 'confirm_warnings' => 1]))
                ->assertSessionHasErrors('registration_number');
        }

        $this->actingAs($this->user)->post('/applicants', $this->payload(['registration_number' => 'A-1000', 'name' => 'Lain', 'confirm_warnings' => 1]))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('applicants', ['registration_number' => 'A-1000', 'registration_seq' => 1000]);
    }

    public function test_number_must_be_unique_per_year_but_not_across_years(): void
    {
        $this->makeApplicant(1);

        $this->actingAs($this->user)->post('/applicants', $this->payload())->assertSessionHasErrors('registration_number');

        $archived = AcademicYear::create(['name' => '2025/2026', 'registration_fee' => 0, 'uniform_threshold_percent' => 0, 'is_active' => false]);
        $this->makeApplicant(7, ['academic_year_id' => $archived->id]);
        $this->actingAs($this->user)->post('/applicants', $this->payload(['registration_number' => 'A-007', 'confirm_warnings' => 1, 'name' => 'Beda']))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Applicant::count() - 1); // 1 aktif lama + 1 baru (+1 arsip)
    }

    public function test_jump_in_number_warns_and_can_be_confirmed(): void
    {
        $this->makeApplicant(5);

        $this->actingAs($this->user)->post('/applicants', $this->payload(['registration_number' => 'A-010']))
            ->assertSessionHas('warnings');
        $this->assertSame(1, Applicant::count());

        $this->actingAs($this->user)->post('/applicants', $this->payload(['registration_number' => 'A-010', 'confirm_warnings' => 1]))
            ->assertSessionMissing('warnings');
        $this->assertSame(2, Applicant::count());
    }

    public function test_next_number_and_gap_filling_do_not_warn(): void
    {
        $this->makeApplicant(1);
        $this->makeApplicant(5);

        $this->actingAs($this->user)->post('/applicants', $this->payload(['registration_number' => 'A-006', 'name' => 'X']))
            ->assertSessionMissing('warnings');
        $this->actingAs($this->user)->post('/applicants', $this->payload(['registration_number' => 'A-003', 'name' => 'Y']))
            ->assertSessionMissing('warnings');
        $this->assertSame(4, Applicant::count());
    }

    public function test_form_shows_last_and_next_number(): void
    {
        $this->makeApplicant(5);

        $this->actingAs($this->user)->get('/applicants/create')->assertOk()->assertSee('A-005')->assertSee('A-006');
    }

    // ---------- data ganda ----------

    public function test_same_person_warns_even_with_different_case_and_spaces(): void
    {
        $this->actingAs($this->user)->post('/applicants', $this->payload())->assertSessionHasNoErrors();

        $second = $this->payload(['registration_number' => 'A-002', 'name' => ' ahmad   FAUZI ', 'mother_name' => 'siti aminah']);
        $this->actingAs($this->user)->post('/applicants', $second)->assertSessionHas('warnings');
        $this->assertSame(1, Applicant::count());

        $this->actingAs($this->user)->post('/applicants', $second + ['confirm_warnings' => 1]);
        $this->assertSame(2, Applicant::count());
    }

    // ---------- validasi ----------

    public function test_phone_is_normalized_and_validated(): void
    {
        $this->actingAs($this->user)->post('/applicants', $this->payload(['phone' => '0812-3456-7890']))->assertSessionHasNoErrors();
        $this->assertSame('081234567890', Applicant::firstOrFail()->phone);

        foreach (['+6281234567890', '0712345678', '08123'] as $bad) {
            $this->actingAs($this->user)->post('/applicants', $this->payload(['registration_number' => 'A-002', 'phone' => $bad]))
                ->assertSessionHasErrors('phone');
        }

        $this->actingAs($this->user)->post('/applicants', $this->payload(['registration_number' => 'A-002', 'phone' => null, 'name' => 'Tanpa HP']))
            ->assertSessionHasNoErrors();
    }

    public function test_address_must_be_consistent_and_complete_to_village(): void
    {
        $this->actingAs($this->user)->post('/applicants', $this->payload(['regency_code' => '33.20']))->assertSessionHasErrors('village_code');
        $this->actingAs($this->user)->post('/applicants', $this->payload(['village_code' => '']))->assertSessionHasErrors('village_code');
        $this->actingAs($this->user)->post('/applicants', $this->payload(['village_code' => '33.19.01']))->assertSessionHasErrors('village_code');
        $this->assertSame(0, Applicant::count());
    }

    public function test_birth_date_cannot_be_in_the_future(): void
    {
        $this->actingAs($this->user)->post('/applicants', $this->payload(['birth_date' => now()->addDay()->format('Y-m-d')]))
            ->assertSessionHasErrors('birth_date');
    }

    public function test_required_fields(): void
    {
        $this->actingAs($this->user)->post('/applicants', [])->assertSessionHasErrors([
            'registration_number', 'name', 'gender', 'birth_place', 'birth_date', 'village_code', 'rt', 'rw',
            'father_name', 'mother_name', 'school_id',
        ]);
    }

    public function test_school_must_be_active_but_referrer_is_optional(): void
    {
        $inactive = School::create(['name' => 'SMP Tutup', 'regency_code' => '33.19', 'regency_name' => 'KAB. KUDUS', 'is_active' => false]);

        $this->actingAs($this->user)->post('/applicants', $this->payload(['school_id' => $inactive->id]))->assertSessionHasErrors('school_id');
        $this->actingAs($this->user)->post('/applicants', $this->payload(['referrer_id' => null]))->assertSessionHasNoErrors();
        $this->assertNull(Applicant::firstOrFail()->referrer_id);
        $this->actingAs($this->user)->post('/applicants', $this->payload(['registration_number' => 'A-002', 'referrer_id' => 9999]))
            ->assertSessionHasErrors('referrer_id');
    }

    public function test_no_active_year_blocks_input(): void
    {
        $this->year->update(['is_active' => false]);

        $this->actingAs($this->user)->get('/applicants/create')->assertForbidden();
        $this->actingAs($this->user)->post('/applicants', $this->payload())->assertForbidden();
    }

    // ---------- ubah, hapus, tahun arsip ----------

    public function test_update_keeps_own_number_logs_changes_and_allows_inactive_current_school(): void
    {
        $applicant = $this->makeApplicant(1);
        $this->school->update(['is_active' => false]);

        $this->actingAs($this->user)->put("/applicants/{$applicant->id}", $this->payload([
            'registration_number' => 'A-001', 'name' => 'Murid Satu Baru', 'mother_name' => 'Ibu 1', 'father_name' => 'Ayah 1',
        ]))->assertRedirect(route('applicants.show', $applicant));

        $this->assertSame('Murid Satu Baru', $applicant->fresh()->name);
        $log = \App\Models\ActivityLog::where('event', 'applicant.updated')->firstOrFail();
        $this->assertSame('Murid Satu Baru', $log->new_values['name']);
        $this->assertSame('Murid 1', $log->old_values['name']);
    }

    public function test_cannot_edit_applicant_from_archived_year(): void
    {
        $archived = AcademicYear::create(['name' => '2025/2026', 'registration_fee' => 0, 'uniform_threshold_percent' => 0, 'is_active' => false]);
        $applicant = $this->makeApplicant(1, ['academic_year_id' => $archived->id]);

        $this->actingAs($this->user)->get("/applicants/{$applicant->id}/edit")->assertForbidden();
        $this->actingAs($this->user)->put("/applicants/{$applicant->id}", $this->payload())->assertForbidden();
        $this->actingAs($this->user)->get("/applicants/{$applicant->id}")->assertOk()->assertSee('arsip');
    }

    public function test_only_admin_can_delete_and_restore_and_deleted_number_stays_reserved(): void
    {
        $applicant = $this->makeApplicant(1);
        $admin = User::factory()->admin()->create();

        $this->actingAs($this->user)->delete("/applicants/{$applicant->id}", ['reason' => 'salah input'])->assertForbidden();

        $this->actingAs($admin)->delete("/applicants/{$applicant->id}", [])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->delete("/applicants/{$applicant->id}", ['reason' => 'salah input'])->assertRedirect('/applicants');
        $this->assertSoftDeleted($applicant);
        $this->assertDatabaseHas('activity_logs', ['event' => 'applicant.deleted', 'reason' => 'salah input']);

        $this->actingAs($this->user)->post('/applicants', $this->payload())->assertSessionHasErrors('registration_number');
        $this->actingAs($this->user)->get("/applicants/{$applicant->id}")->assertNotFound();

        $this->actingAs($admin)->patch("/applicants/{$applicant->id}/restore", ['reason' => 'ternyata benar'])->assertRedirect();
        $this->assertNull($applicant->fresh()->deleted_at);
    }

    // ---------- daftar, akses, cetak ----------

    public function test_index_search_and_filter(): void
    {
        $this->makeApplicant(1, ['name' => 'Budi Hartono']);
        $this->makeApplicant(2, ['name' => 'Siti Rahma']);

        $this->actingAs($this->user)->get('/applicants?q=budi')->assertSee('Budi Hartono')->assertDontSee('Siti Rahma');
        $this->actingAs($this->user)->get('/applicants?q=A-002')->assertSee('Siti Rahma')->assertDontSee('Budi Hartono');
        $this->actingAs($this->user)->get('/applicants?school_id='.$this->school->id)->assertSee('Budi Hartono')->assertSee('Siti Rahma');
    }

    public function test_only_admin_can_view_archived_year_list(): void
    {
        $archived = AcademicYear::create(['name' => '2025/2026', 'registration_fee' => 0, 'uniform_threshold_percent' => 0, 'is_active' => false]);
        $this->makeApplicant(1, ['name' => 'Murid Lama', 'academic_year_id' => $archived->id]);
        $this->makeApplicant(2, ['name' => 'Murid Baru']);

        $this->actingAs($this->user)->get("/applicants?year={$archived->id}")->assertSee('Murid Baru')->assertDontSee('Murid Lama');
        $this->actingAs(User::factory()->admin()->create())->get("/applicants?year={$archived->id}")
            ->assertSee('Murid Lama')->assertDontSee('Murid Baru');
    }

    public function test_other_roles_cannot_access_applicants(): void
    {
        $applicant = $this->makeApplicant(1);

        foreach ([Role::DaftarUlang, Role::Seragam] as $role) {
            $this->actingAs(User::factory()->role($role)->create());
            $this->get('/applicants')->assertForbidden();
            $this->get("/applicants/{$applicant->id}")->assertForbidden();
            $this->post('/applicants', $this->payload())->assertForbidden();
        }
    }

    public function test_show_has_history_and_print_page_renders(): void
    {
        config(['sekolah.name' => 'SMA CONTOH 1']);
        $this->actingAs($this->user)->post('/applicants', $this->payload());
        $applicant = Applicant::firstOrFail();

        $this->actingAs($this->user)->get("/applicants/{$applicant->id}")
            ->assertOk()->assertSee('Riwayat')->assertSee('ditambahkan');

        $this->actingAs($this->user)->get("/applicants/{$applicant->id}/print")
            ->assertOk()->assertSee('A-001')->assertSee('Ahmad Fauzi')->assertSee('SMA CONTOH 1');
    }

    public function test_dashboard_shows_applicant_stats_for_registration_staff(): void
    {
        $this->makeApplicant(1);
        $this->makeApplicant(5);

        $this->actingAs($this->user)->get('/dashboard')->assertOk()->assertSee('Nomor terakhir dipakai')->assertSee('A-005');
    }

    // ---------- penggabungan pembawa ----------

    public function test_admin_can_merge_referrers_and_applicants_move_with_history(): void
    {
        $admin = User::factory()->admin()->create();
        $duplicate = Referrer::create(['name' => 'Budi Santosa', 'name_key' => 'budi santosa']);
        $a = $this->makeApplicant(1, ['referrer_id' => $duplicate->id]);
        $b = $this->makeApplicant(2, ['referrer_id' => $this->referrer->id]);
        $c = $this->makeApplicant(3, ['referrer_id' => $duplicate->id]);
        $c->delete(); // data terhapus ikut dipindahkan

        $this->actingAs($admin)->post("/referrers/{$duplicate->id}/merge", ['target_id' => $this->referrer->id])
            ->assertRedirect('/referrers');

        $this->assertDatabaseMissing('referrers', ['id' => $duplicate->id]);
        $this->assertSame($this->referrer->id, $a->fresh()->referrer_id);
        $this->assertSame($this->referrer->id, Applicant::withTrashed()->find($c->id)->referrer_id);
        $this->assertDatabaseHas('activity_logs', ['event' => 'referrer.merged']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'applicant.referrer_changed', 'subject_id' => $a->id]);
        $this->assertDatabaseMissing('activity_logs', ['event' => 'applicant.referrer_changed', 'subject_id' => $b->id]);
    }

    public function test_merge_into_self_or_by_non_admin_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post("/referrers/{$this->referrer->id}/merge", ['target_id' => $this->referrer->id])
            ->assertSessionHas('error');
        $this->assertDatabaseHas('referrers', ['id' => $this->referrer->id]);

        $this->actingAs($this->user)->post("/referrers/{$this->referrer->id}/merge", ['target_id' => 1])->assertForbidden();
    }

    public function test_referrer_admin_page_shows_applicant_counts(): void
    {
        $this->makeApplicant(1, ['referrer_id' => $this->referrer->id]);
        $this->makeApplicant(2, ['referrer_id' => $this->referrer->id]);

        $this->actingAs(User::factory()->admin()->create())->get('/referrers')->assertOk()->assertSee('Gabungkan');
    }
}
