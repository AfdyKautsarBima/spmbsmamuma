<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\Payment;
use App\Models\Region;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApplicantTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year;
    private School $school;
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
            'referrer_name'       => 'Budi Santoso',
            'referrer_class_name' => 'XI IPA 1',
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

    public function test_student_phone_is_optional_separate_and_validated(): void
    {
        $this->actingAs($this->user)->post('/applicants', $this->payload([
            'student_phone' => '0812-3456-7891',
        ]))->assertSessionHasNoErrors();

        $applicant = Applicant::firstOrFail();
        $this->assertSame('081234567891', $applicant->student_phone);
        $this->assertSame('081234567890', $applicant->phone);

        $this->actingAs($this->user)->post('/applicants', $this->payload([
            'registration_number' => 'A-002',
            'name' => 'Tanpa HP Siswa',
            'student_phone' => null,
        ]))->assertSessionHasNoErrors();
        $this->assertNull(Applicant::where('registration_number', 'A-002')->firstOrFail()->student_phone);

        $this->actingAs($this->user)->post('/applicants', $this->payload([
            'registration_number' => 'A-003',
            'name' => 'HP Siswa Tidak Valid',
            'student_phone' => '0712345678',
        ]))->assertSessionHasErrors('student_phone');
    }

    public function test_whatsapp_contact_is_only_shown_for_applicants_who_have_not_re_enrolled(): void
    {
        $applicant = $this->makeApplicant(1, [
            'phone' => '081234567890',
            'student_phone' => '081234567891',
        ]);

        $this->actingAs($this->user)->get(route('applicants.show', $applicant))
            ->assertOk()
            ->assertSee('Hubungi via WhatsApp')
            ->assertSee('https://wa.me/6281234567890')
            ->assertSee('Nomor HP siswa')
            ->assertSee('081234567891')
            ->assertSee('Nomor HP orang tua/wali');

        $this->actingAs($this->user)->get(route('applicants.print', $applicant))
            ->assertOk()
            ->assertSee('Nomor HP siswa')
            ->assertSee('081234567891')
            ->assertSee('Nomor HP orang tua/wali');

        Payment::create([
            'applicant_id' => $applicant->id,
            'academic_year_id' => $this->year->id,
            'receipt_seq' => 1,
            'receipt_number' => 'K-001',
            'amount' => 100000,
            'paid_at' => now(),
            'received_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)->get(route('applicants.show', $applicant))
            ->assertOk()
            ->assertDontSee('Hubungi via WhatsApp');

        $withoutPhone = $this->makeApplicant(2);
        $this->actingAs($this->user)->get(route('applicants.show', $withoutPhone))
            ->assertOk()
            ->assertDontSee('Hubungi via WhatsApp');
    }

    public function test_whatsapp_template_is_prefilled_and_applies_applicant_placeholders(): void
    {
        $applicant = $this->makeApplicant(1, ['phone' => '081234567890']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('whatsapp-template.update'), [
            'message' => 'Halo {nama}, nomor pendaftaran {nomor_pendaftaran}.',
        ])->assertRedirect(route('whatsapp-template.edit'));

        $this->actingAs($admin)->get(route('whatsapp-template.edit'))
            ->assertOk()
            ->assertSee('Template WA')
            ->assertSee('{nama}');

        $this->actingAs($this->user)->get(route('applicants.show', $applicant))
            ->assertOk()
            ->assertSee('https://wa.me/6281234567890?text=Halo%20Murid%201%2C%20nomor%20pendaftaran%20A-001.');
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
        $this->actingAs($this->user)->post('/applicants', $this->payload([
            'referrer_name' => null,
            'referrer_class_name' => null,
        ]))->assertSessionHasNoErrors();
        $this->assertNull(Applicant::firstOrFail()->referrer_name);
        $this->actingAs($this->user)->post('/applicants', $this->payload([
            'registration_number' => 'A-002',
            'referrer_name' => str_repeat('x', 101),
        ]))->assertSessionHasErrors('referrer_name');
        $this->actingAs($this->user)->post('/applicants', $this->payload([
            'registration_number' => 'A-003',
            'referrer_name' => null,
            'referrer_class_name' => 'XI IPA 1',
        ]))->assertSessionHasErrors('referrer_name');
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

    public function test_admin_can_permanently_delete_soft_deleted_applicant(): void
    {
        $applicant = $this->makeApplicant(1);
        $admin = User::factory()->admin()->create();
        $applicant->delete();

        $this->actingAs($admin)->get("/applicants/{$applicant->id}")
            ->assertOk()
            ->assertSee('Hapus permanen');

        $this->actingAs($admin)->delete("/applicants/{$applicant->id}/permanent", ['reason' => 'data duplikat'])
            ->assertRedirect('/applicants');

        $this->assertDatabaseMissing('applicants', ['id' => $applicant->id]);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'applicant.force_deleted',
            'subject_id' => $applicant->id,
            'reason' => 'data duplikat',
        ]);
    }

    public function test_permanent_delete_removes_cancelled_payments_but_keeps_activity_logs(): void
    {
        $applicant = $this->makeApplicant(1);
        $admin = User::factory()->admin()->create();
        $payment = Payment::create([
            'applicant_id' => $applicant->id,
            'academic_year_id' => $this->year->id,
            'receipt_seq' => 1,
            'receipt_number' => 'K-001',
            'amount' => 100000,
            'paid_at' => now(),
            'received_by' => $this->user->id,
        ]);

        $this->actingAs($admin)->delete("/applicants/{$applicant->id}", ['reason' => 'salah input'])->assertSessionHas('error');
        app(\App\Services\EnrollmentService::class)->cancelPayment($payment, 'salah input', $admin);
        $this->actingAs($admin)->delete("/applicants/{$applicant->id}", ['reason' => 'salah input'])->assertRedirect();

        $this->actingAs($this->user)->delete("/applicants/{$applicant->id}/permanent", ['reason' => 'data duplikat'])
            ->assertForbidden();

        $this->actingAs($admin)->delete("/applicants/{$applicant->id}/permanent", ['reason' => 'data duplikat'])
            ->assertRedirect('/applicants');
        $this->assertDatabaseMissing('applicants', ['id' => $applicant->id]);
        $this->assertDatabaseMissing('payments', ['applicant_id' => $applicant->id, 'receipt_number' => 'K-001']);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'applicant.force_deleted',
            'subject_id' => $applicant->id,
            'reason' => 'data duplikat',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'payment.cancelled',
            'subject_id' => $applicant->id,
            'reason' => 'salah input',
        ]);
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

    public function test_referrer_name_and_class_are_saved_on_each_applicant_without_admin_master(): void
    {
        $payload = $this->payload([
            'referrer_name' => '  Budi   Santoso ',
            'referrer_class_name' => ' XI   IPA 1 ',
        ]);

        $this->actingAs($this->user)->post('/applicants', $payload)->assertSessionHasNoErrors();

        $applicant = Applicant::firstOrFail();
        $this->assertSame('Budi Santoso', $applicant->referrer_name);
        $this->assertSame('XI IPA 1', $applicant->referrer_class_name);
        $this->assertFalse(Schema::hasColumn('applicants', 'referrer_id'));
        $this->assertFalse(Schema::hasTable('referrers'));
        $this->actingAs(User::factory()->admin()->create())->get('/referrers')->assertNotFound();
    }

    public function test_referrer_class_can_be_selected_or_entered_as_other(): void
    {
        $this->actingAs($this->user)->post('/applicants', $this->payload([
            'referrer_class_option' => 'X.1',
            'referrer_class_other' => '',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('X.1', Applicant::firstOrFail()->referrer_class_name);

        $this->actingAs($this->user)->post('/applicants', $this->payload([
            'registration_number' => 'A-002',
            'name' => 'Murid Kedua',
            'mother_name' => 'Ibu Kedua',
            'referrer_class_option' => 'lainnya',
            'referrer_class_other' => ' XI   IPA 1 ',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('XI IPA 1', Applicant::where('registration_number', 'A-002')->firstOrFail()->referrer_class_name);

        $this->actingAs($this->user)->post('/applicants', $this->payload([
            'registration_number' => 'A-003',
            'name' => 'Murid Ketiga',
            'mother_name' => 'Ibu Ketiga',
            'referrer_class_option' => 'lainnya',
            'referrer_class_other' => '',
        ]))->assertSessionHasErrors('referrer_class_other');
    }

    public function test_existing_referrer_values_are_copied_to_applicants_by_the_migration(): void
    {
        $existing = $this->makeApplicant(1, [
            'referrer_name' => 'Siti Aminah',
            'referrer_class_name' => 'XII IPS 2',
        ]);
        $migration = require database_path('migrations/2026_11_04_000003_move_referrer_data_to_applicants_table.php');
        $migration->down();

        $this->assertNotNull($existing->fresh()->referrer_id);
        $referrerId = DB::table('referrers')->insertGetId([
            'name' => 'Budi Santoso',
            'name_key' => 'budi santoso',
            'class_name' => 'XI IPA 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $applicant = $this->makeApplicant(2);
        DB::table('applicants')->where('id', $applicant->id)->update(['referrer_id' => $referrerId]);

        $migration->up();

        $this->assertSame('Budi Santoso', $applicant->fresh()->referrer_name);
        $this->assertSame('XI IPA 1', $applicant->fresh()->referrer_class_name);
        $this->assertSame('Siti Aminah', $existing->fresh()->referrer_name);
        $this->assertSame('XII IPS 2', $existing->fresh()->referrer_class_name);
        $this->assertFalse(Schema::hasColumn('applicants', 'referrer_id'));
        $this->assertFalse(Schema::hasTable('referrers'));
    }
}
