<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\ApplicantUniformSize;
use App\Models\Payment;
use App\Models\School;
use App\Models\UniformEntitlement;
use App\Models\UniformHandover;
use App\Models\UniformSize;
use App\Models\UniformType;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\UniformService;
use App\Services\UniformStats;
use Carbon\Carbon;
use Database\Seeders\UniformSizeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniformTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year;
    private School $school;
    private User $clerk;
    private User $staff;
    private User $admin;
    private User $reg;
    private UniformType $shirt;
    private UniformType $pe;
    private UniformType $batik;
    private UniformSize $sizeM;
    private UniformSize $sizeL;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-12-05 10:00:00');

        $this->year = AcademicYear::create(['name' => '2026/2027', 'registration_fee' => 1500000, 'uniform_threshold_amount' => 900000, 'is_active' => true]);
        $this->school = School::create(['name' => 'SMP 1', 'regency_code' => '33.19', 'regency_name' => 'KAB. KUDUS']);
        $this->clerk = User::factory()->role(Role::DaftarUlang)->create();
        $this->staff = User::factory()->role(Role::Seragam)->create();
        $this->admin = User::factory()->admin()->create();
        $this->reg = User::factory()->create();

        UniformSize::create(['name' => 'S', 'sort_order' => 10, 'is_active' => true]);
        $this->sizeM = UniformSize::create(['name' => 'M', 'sort_order' => 20, 'is_active' => true]);
        $this->sizeL = UniformSize::create(['name' => 'L', 'sort_order' => 30, 'is_active' => true]);

        $this->shirt = UniformType::create(['academic_year_id' => $this->year->id, 'name' => 'Baju Putih', 'sort_order' => 10, 'is_active' => true]);
        $this->pe = UniformType::create(['academic_year_id' => $this->year->id, 'name' => 'Olahraga', 'needs_size' => true, 'sort_order' => 20, 'is_active' => true]);
        $this->batik = UniformType::create(['academic_year_id' => $this->year->id, 'name' => 'Batik', 'sort_order' => 30, 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function applicant(int $seq = 1, array $override = []): Applicant
    {
        return Applicant::create(array_merge([
            'academic_year_id' => $this->year->id,
            'registration_number' => Applicant::formatNumber($seq), 'registration_seq' => $seq,
            'name' => 'Murid '.$seq, 'gender' => 'P', 'birth_place' => 'Kudus', 'birth_date' => '2010-01-01',
            'province_code' => '33', 'province_name' => 'JAWA TENGAH', 'regency_code' => '33.19', 'regency_name' => 'KAB. KUDUS',
            'district_code' => '33.19.01', 'district_name' => 'KALIWUNGU', 'village_code' => '33.19.01.2001', 'village_name' => 'BAKALAN',
            'rt' => '001', 'rw' => '001', 'father_name' => 'Ayah '.$seq, 'mother_name' => 'Ibu '.$seq, 'phone' => '081234567890',
            'school_id' => $this->school->id,
            'person_key' => Applicant::personKey('Murid '.$seq, '2010-01-01', 'Ibu '.$seq),
            'created_by' => $this->reg->id,
        ], $override));
    }

    private function pay(Applicant $a, int $amount): Payment
    {
        return app(EnrollmentService::class)->addPayment($a, $amount, null, $this->clerk);
    }

    private function entitled(int $seq): Applicant
    {
        $a = $this->applicant($seq);
        $this->pay($a, 900000);
        $this->assertTrue($a->uniformEntitlement()->exists(), 'murid seharusnya berhak');

        return $a;
    }

    private function svc(): UniformService
    {
        return app(UniformService::class);
    }

    private function state(Applicant $a): array
    {
        return $this->svc()->state($a->fresh());
    }

    // ---------- hak ----------

    public function test_threshold_amount_rounds_up(): void
    {
        $this->assertSame(600001, UniformService::thresholdAmount(1000001, 60));
        $this->assertSame(600000, UniformService::thresholdAmount(1000000, 60));
        $this->assertSame(0, UniformService::thresholdAmount(1500000, 0));
        $this->assertSame(1500000, UniformService::thresholdAmount(1500000, 100));
    }

    public function test_entitlement_is_granted_when_payments_reach_the_threshold_and_stores_the_basis(): void
    {
        $a = $this->applicant();

        $this->pay($a, 500000);
        $this->assertFalse($a->uniformEntitlement()->exists());
        $this->assertSame('belum_berhak', $this->state($a)['status']);
        $this->assertSame(400000, $this->state($a)['shortage']);

        $this->pay($a, 400000);
        $entitlement = $a->fresh()->uniformEntitlement;
        $this->assertNotNull($entitlement);
        $this->assertSame(0, $entitlement->threshold_percent);
        $this->assertSame(900000, $entitlement->threshold_amount);
        $this->assertSame(1500000, $entitlement->net_bill);
        $this->assertSame('berhak', $this->state($a)['status']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'uniform.entitled', 'subject_id' => $a->id]);
    }

    public function test_manual_discount_does_not_change_the_fixed_uniform_threshold(): void
    {
        $a = $this->applicant();
        app(EnrollmentService::class)->setManualDiscount($a, 20, 'Anak yatim', $this->admin); // tagihan bersih 1.200.000

        $this->pay($a, 899999);
        $this->assertFalse($a->uniformEntitlement()->exists());
        $this->pay($a, 1);
        $this->assertSame(900000, $a->fresh()->uniformEntitlement->threshold_amount);
    }

    public function test_zero_amount_threshold_grants_at_first_payment(): void
    {
        $this->year->update(['uniform_threshold_amount' => 0]);
        $a = $this->applicant();

        $this->pay($a, 1000);
        $this->assertSame(0, $a->fresh()->uniformEntitlement->threshold_amount);
    }

    public function test_no_entitlement_before_first_payment(): void
    {
        $a = $this->applicant();
        app(EnrollmentService::class)->setManualDiscount($a, 50, 'Keringanan', $this->admin);

        $this->assertFalse($a->uniformEntitlement()->exists());
        $this->assertSame('belum_bayar', $this->state($a)['status']);
    }

    public function test_raising_threshold_keeps_rights_and_lowering_grants_new_ones(): void
    {
        $a = $this->applicant(1);
        $b = $this->applicant(2);
        $this->pay($a, 900000);
        $this->pay($b, 500000);
        $put = fn (int $amount) => $this->actingAs($this->admin)->put("/academic-years/{$this->year->id}", [
            'name' => '2026/2027', 'registration_fee' => 1500000, 'uniform_threshold_amount' => $amount,
        ]);

        $put(1350000)->assertSessionHasNoErrors();
        $this->assertTrue($a->uniformEntitlement()->exists());   // hak yang sudah ada tetap
        $this->assertFalse($b->uniformEntitlement()->exists());

        $put(450000)->assertSessionHas('success', fn ($m) => str_contains($m, '1 murid mendapat hak seragam baru'));
        $this->assertSame(0, $b->fresh()->uniformEntitlement->threshold_percent);
        $this->assertSame(450000, $b->fresh()->uniformEntitlement->threshold_amount);
        $this->assertSame(900000, $a->fresh()->uniformEntitlement->threshold_amount);  // dasar hak lama tidak berubah
    }

    public function test_cancelling_payment_revokes_unused_right_but_keeps_it_with_a_warning_after_pickup(): void
    {
        $svc = app(EnrollmentService::class);

        $unused = $this->applicant(1);
        $this->pay($unused, 500000);
        $second = $this->pay($unused, 400000);
        $this->assertTrue($unused->uniformEntitlement()->exists());
        $svc->cancelPayment($second, 'salah input', $this->admin);
        $this->assertFalse($unused->fresh()->uniformEntitlement()->exists());
        $this->assertDatabaseHas('activity_logs', ['event' => 'uniform.entitlement_revoked', 'subject_id' => $unused->id]);

        $used = $this->applicant(2);
        $this->pay($used, 500000);
        $payment = $this->pay($used, 400000);
        $this->svc()->handOver($used, $this->shirt, $this->staff);
        $svc->cancelPayment($payment, 'salah input', $this->admin);

        $this->assertTrue($used->fresh()->uniformEntitlement()->exists());
        $this->assertStringContainsString('di bawah ambang', implode(' ', $this->state($used)['warnings']));
    }

    public function test_manual_discount_does_not_reduce_the_uniform_threshold(): void
    {
        $a = $this->applicant(1);
        $this->pay($a, 500000);
        $this->assertFalse($a->uniformEntitlement()->exists());

        app(EnrollmentService::class)->setManualDiscount($a, 50, 'Penyesuaian', $this->admin);
        $this->assertFalse($a->uniformEntitlement()->exists());

        app(EnrollmentService::class)->setManualDiscount($a, 0, 'Dibatalkan', $this->admin);
        $this->assertFalse($a->uniformEntitlement()->exists());
    }

    public function test_withdrawn_students_are_not_granted_rights_but_keep_existing_ones(): void
    {
        $svc = app(EnrollmentService::class);

        $keeps = $this->entitled(1);
        $svc->withdraw($keeps, '2026-12-05', 'Pindah', $this->admin);
        $this->assertTrue($keeps->fresh()->uniformEntitlement()->exists());

        $gone = $this->applicant(2);
        $this->pay($gone, 500000);
        $svc->withdraw($gone, '2026-12-05', 'Pindah', $this->admin);
        $this->year->update(['uniform_threshold_amount' => 450000]);
        $this->svc()->syncYear($this->year->fresh());
        $this->assertFalse($gone->fresh()->uniformEntitlement()->exists());

        $svc->cancelWithdrawal($gone->fresh(), 'Ternyata jadi', $this->admin);   // setelah aktif lagi, hak dievaluasi
        $this->assertTrue($gone->fresh()->uniformEntitlement()->exists());
    }

    // ---------- penyerahan ----------

    private function handover(Applicant $a, UniformType $t, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->staff)->post("/seragam/{$a->id}/serah/{$t->id}");
    }

    private function chooseSize(Applicant $a, UniformType $t, UniformSize $s, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->staff)->post("/seragam/{$a->id}/ukuran/{$t->id}", ['uniform_size_id' => $s->id]);
    }

    public function test_handover_requires_entitlement_and_cannot_be_repeated(): void
    {
        $poor = $this->applicant(1);
        $this->pay($poor, 100000);
        $this->handover($poor, $this->shirt)->assertSessionHas('error', fn ($m) => str_contains($m, 'belum berhak'));
        $this->assertSame(0, UniformHandover::count());

        $a = $this->entitled(2);
        $this->handover($a, $this->shirt)->assertSessionHas('success');
        $handover = UniformHandover::firstOrFail();
        $this->assertSame($this->staff->id, $handover->handed_by);
        $this->assertDatabaseHas('activity_logs', ['event' => 'uniform.handed_over', 'subject_id' => $a->id]);

        $this->handover($a, $this->shirt)->assertSessionHas('error', fn ($m) => str_contains($m, 'sudah diserahkan'));
        $this->assertSame(1, UniformHandover::count());
    }

    public function test_sized_uniform_needs_a_recorded_size_before_handover(): void
    {
        $a = $this->entitled(1);

        $this->handover($a, $this->pe)->assertSessionHas('error', fn ($m) => str_contains($m, 'ukuran'));
        $this->chooseSize($a, $this->pe, $this->sizeM)->assertSessionHas('success');
        $this->handover($a, $this->pe)->assertSessionHas('success');

        $this->assertSame($this->sizeM->id, UniformHandover::firstOrFail()->uniform_size_id);
    }

    public function test_inactive_type_and_withdrawn_student_cannot_receive(): void
    {
        $a = $this->entitled(1);
        $this->batik->update(['is_active' => false]);
        $this->handover($a, $this->batik)->assertSessionHas('error', fn ($m) => str_contains($m, 'tidak aktif'));

        app(EnrollmentService::class)->withdraw($a, '2026-12-05', 'Pindah', $this->admin);
        $this->handover($a, $this->shirt)->assertSessionHas('error', fn ($m) => str_contains($m, 'mengundurkan diri'));
        $this->assertSame(0, UniformHandover::count());
    }

    public function test_size_rules(): void
    {
        $poor = $this->applicant(1);
        $this->pay($poor, 100000);
        $this->chooseSize($poor, $this->pe, $this->sizeM)->assertSessionHas('error', fn ($m) => str_contains($m, 'berhak'));

        $a = $this->entitled(2);
        $this->chooseSize($a, $this->shirt, $this->sizeM)->assertSessionHas('error', fn ($m) => str_contains($m, 'tidak memakai ukuran'));

        $this->chooseSize($a, $this->pe, $this->sizeM)->assertSessionHas('success');
        $this->chooseSize($a, $this->pe, $this->sizeL)->assertSessionHas('success'); // boleh diubah selama belum diambil
        $this->assertSame($this->sizeL->id, ApplicantUniformSize::firstOrFail()->uniform_size_id);

        $this->sizeM->update(['is_active' => false]);
        $this->chooseSize($a, $this->pe, $this->sizeM)->assertSessionHas('error', fn ($m) => str_contains($m, 'tidak aktif'));
    }

    public function test_after_pickup_only_admin_can_change_the_size(): void
    {
        $a = $this->entitled(1);
        $this->chooseSize($a, $this->pe, $this->sizeM);
        $this->handover($a, $this->pe);

        $this->chooseSize($a, $this->pe, $this->sizeL)->assertSessionHas('error', fn ($m) => str_contains($m, 'Hanya admin'));
        $this->assertSame($this->sizeM->id, ApplicantUniformSize::firstOrFail()->uniform_size_id);

        $this->chooseSize($a, $this->pe, $this->sizeL, $this->admin)->assertSessionHas('success');
        $this->assertSame($this->sizeL->id, ApplicantUniformSize::firstOrFail()->uniform_size_id);
        $this->assertSame($this->sizeL->id, UniformHandover::firstOrFail()->uniform_size_id);
    }

    public function test_cancelling_a_handover_same_day_by_staff_or_later_by_admin_with_reason(): void
    {
        $a = $this->entitled(1);
        $this->handover($a, $this->shirt);
        $this->handover($a, $this->batik);

        $this->actingAs($this->staff)->post("/seragam/{$a->id}/batal/{$this->shirt->id}")->assertSessionHas('success');
        $this->assertSame(1, UniformHandover::count());
        $this->assertDatabaseHas('activity_logs', ['event' => 'uniform.handover_cancelled', 'subject_id' => $a->id]);

        Carbon::setTestNow('2026-12-06 10:00:00');
        $this->actingAs($this->staff)->post("/seragam/{$a->id}/batal/{$this->batik->id}", ['reason' => 'salah'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'hanya bisa dilakukan admin'));
        $this->actingAs($this->admin)->post("/seragam/{$a->id}/batal/{$this->batik->id}")
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Alasan wajib'));
        $this->assertSame(1, UniformHandover::count());

        $this->actingAs($this->admin)->post("/seragam/{$a->id}/batal/{$this->batik->id}", ['reason' => 'Dikembalikan murid'])
            ->assertSessionHas('success');
        $this->assertSame(0, UniformHandover::count());
        $this->assertDatabaseHas('activity_logs', ['event' => 'uniform.handover_cancelled', 'reason' => 'Dikembalikan murid']);
    }

    public function test_withdrawn_student_with_pickup_shows_a_warning(): void
    {
        $a = $this->entitled(1);
        $this->handover($a, $this->shirt);
        app(EnrollmentService::class)->withdraw($a, '2026-12-05', 'Pindah', $this->admin);

        $this->assertStringContainsString('mengundurkan diri', implode(' ', $this->state($a)['warnings']));
        $this->actingAs($this->staff)->get("/seragam/{$a->id}")->assertOk()->assertSee('Pengembalian seragam diurus di luar sistem');
    }

    // ---------- akses dan privasi ----------

    public function test_access_by_role(): void
    {
        $a = $this->entitled(1);

        foreach ([$this->reg, $this->clerk] as $user) {
            $this->actingAs($user)->get('/seragam')->assertForbidden();
            $this->actingAs($user)->get("/seragam/{$a->id}")->assertForbidden();
            $this->actingAs($user)->post("/seragam/{$a->id}/serah/{$this->shirt->id}")->assertForbidden();
        }
        foreach ([$this->staff, $this->admin] as $user) {
            $this->actingAs($user)->get('/seragam')->assertOk();
            $this->actingAs($user)->get("/seragam/{$a->id}")->assertOk();
        }
    }

    public function test_uniform_staff_sees_payment_amounts_but_no_personal_data(): void
    {
        $a = $this->entitled(1);

        $this->actingAs($this->staff)->get("/seragam/{$a->id}")
            ->assertOk()->assertSee('Rp 900.000')->assertSee('Rp 1.500.000')->assertSee('SMP 1')
            ->assertDontSee('BAKALAN')->assertDontSee('KALIWUNGU')->assertDontSee('081234567890')->assertDontSee('Ayah 1')->assertDontSee('Ibu 1');
        $this->actingAs($this->staff)->get('/seragam')->assertOk()->assertDontSee('081234567890')->assertDontSee('BAKALAN');
    }

    // ---------- daftar dan filter ----------

    private function scenario(): array
    {
        $svc = $this->svc();

        $notEntitled = $this->applicant(1, ['name' => 'Murid A']);
        $this->pay($notEntitled, 100000);

        $none = $this->entitled(2);
        $none->update(['name' => 'Murid B']);

        $partial = $this->entitled(3);
        $partial->update(['name' => 'Murid C']);
        $svc->handOver($partial, $this->shirt, $this->staff);

        $complete = $this->entitled(4);
        $complete->update(['name' => 'Murid D']);
        $svc->setSize($complete, $this->pe, $this->sizeM, $this->staff);
        foreach ([$this->shirt, $this->pe, $this->batik] as $type) {
            $svc->handOver($complete, $type, $this->staff);
        }

        $gone = $this->entitled(5);
        $gone->update(['name' => 'Murid E']);
        $svc->handOver($gone, $this->shirt, $this->staff);
        app(EnrollmentService::class)->withdraw($gone, '2026-12-05', 'Pindah', $this->admin);

        return compact('notEntitled', 'none', 'partial', 'complete', 'gone');
    }

    public function test_index_filters(): void
    {
        $this->scenario();
        $names = ['A', 'B', 'C', 'D', 'E'];
        $expect = [
            'berhak'       => ['B', 'C', 'D'],
            'belum_ambil'  => ['B'],
            'sebagian'     => ['C'],
            'lengkap'      => ['D'],
            'belum_berhak' => ['A'],
            'semua'        => ['A', 'B', 'C', 'D'],
            'mundur_ambil' => ['E'],
        ];

        foreach ($expect as $status => $shown) {
            $response = $this->actingAs($this->staff)->get("/seragam?status={$status}")->assertOk();
            foreach ($names as $n) {
                in_array($n, $shown, true) ? $response->assertSee("Murid {$n}") : $response->assertDontSee("Murid {$n}");
            }
        }
        $this->actingAs($this->staff)->get('/seragam')->assertSee('Murid B')->assertDontSee('Murid A'); // bawaan: yang berhak
    }

    public function test_stats_and_dashboards(): void
    {
        $this->scenario();

        $stats = app(UniformStats::class)->forYear($this->year);
        $this->assertSame(3, $stats['entitled']);
        $this->assertSame(1, $stats['not_picked']);
        $this->assertSame(1, $stats['partial']);
        $this->assertSame(1, $stats['complete']);
        $this->assertSame(1, $stats['withdrawn_with_uniform']);
        $this->assertSame(3, $stats['type_count']);
        $this->assertSame(5, $stats['handed_today']);
        $this->assertSame(3, $stats['students_today']);
        $this->assertSame(['M' => 1], $stats['size_recap']['Olahraga']['sizes']);
        $this->assertSame(2, $stats['size_recap']['Olahraga']['missing']);

        $this->actingAs($this->staff)->get('/dashboard')->assertOk()->assertSee('Berhak belum ambil')->assertSee('Kebutuhan ukuran Olahraga');
        $this->actingAs($this->admin)->get('/dashboard')->assertOk()->assertSee('murid mundur dengan seragam sudah diambil');
        $this->actingAs($this->reg)->get('/dashboard')->assertOk()->assertDontSee('Berhak belum ambil');
    }

    public function test_recalculate_command_grants_missing_rights(): void
    {
        $a = $this->applicant();
        $this->pay($a, 500000);
        $this->year->update(['uniform_threshold_amount' => 450000]); // tanpa sinkronisasi
        $this->assertFalse($a->uniformEntitlement()->exists());

        $this->artisan('seragam:hitung-ulang')->assertSuccessful();

        $this->assertTrue($a->fresh()->uniformEntitlement()->exists());
        $this->assertSame(1, UniformEntitlement::count());
    }

    public function test_proof_page(): void
    {
        config(['sekolah.name' => 'SMA CONTOH 1']);
        $a = $this->entitled(1);
        $this->chooseSize($a, $this->pe, $this->sizeM);
        $this->handover($a, $this->pe);

        $this->actingAs($this->staff)->get("/seragam/{$a->id}/bukti")
            ->assertOk()->assertSee('Bukti Serah Terima Seragam')->assertSee('SMA CONTOH 1')->assertSee('Olahraga')
            ->assertSee($this->staff->name)->assertSee('Belum diambil');
    }

    // ---------- master seragam ----------

    public function test_admin_manages_uniform_types(): void
    {
        $this->actingAs($this->admin)->post('/uniform-types', ['name' => ' Atribut  Sekolah ', 'sort_order' => 40])->assertSessionHasNoErrors();
        $type = UniformType::where('name', 'Atribut Sekolah')->firstOrFail();
        $this->assertFalse($type->needs_size);
        $this->assertTrue($type->is_active);
        $this->assertSame($this->year->id, $type->academic_year_id);

        $this->actingAs($this->admin)->post('/uniform-types', ['name' => 'Atribut Sekolah'])->assertSessionHasErrors('name');

        $this->actingAs($this->admin)->put("/uniform-types/{$type->id}", ['name' => 'Atribut', 'needs_size' => '1', 'sort_order' => 5])
            ->assertSessionHasNoErrors();
        $this->assertTrue($type->fresh()->needs_size);

        $this->actingAs($this->admin)->patch("/uniform-types/{$type->id}/toggle-active");
        $this->assertFalse($type->fresh()->is_active);
        $this->assertDatabaseHas('activity_logs', ['event' => 'uniform_type.deactivated']);

        foreach ([$this->staff, $this->reg, $this->clerk] as $user) {
            $this->actingAs($user)->get('/uniform-types')->assertForbidden();
            $this->actingAs($user)->post('/uniform-types', ['name' => 'X'])->assertForbidden();
        }
    }

    public function test_uniform_types_of_archived_year_are_read_only_and_can_be_copied(): void
    {
        $old = AcademicYear::create(['name' => '2025/2026', 'registration_fee' => 0, 'uniform_threshold_amount' => 0, 'is_active' => false]);
        $oldType = UniformType::create(['academic_year_id' => $old->id, 'name' => 'Pramuka', 'needs_size' => true]);
        UniformType::create(['academic_year_id' => $old->id, 'name' => 'Batik']); // sudah ada di tahun aktif

        $this->actingAs($this->admin)->get("/uniform-types/{$oldType->id}/edit")->assertForbidden();
        $this->actingAs($this->admin)->patch("/uniform-types/{$oldType->id}/toggle-active")->assertForbidden();

        $this->actingAs($this->admin)->post('/uniform-types/copy', ['from_year' => $old->id])->assertSessionHas('success', fn ($m) => str_contains($m, '1 jenis'));
        $copied = UniformType::where('academic_year_id', $this->year->id)->where('name', 'Pramuka')->firstOrFail();
        $this->assertTrue($copied->needs_size);
        $this->assertSame(1, UniformType::where('academic_year_id', $this->year->id)->where('name', 'Batik')->count());
    }

    public function test_admin_manages_sizes_and_seeder_is_idempotent(): void
    {
        $this->actingAs($this->admin)->post('/uniform-sizes', ['name' => 'XS', 'sort_order' => 5])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post('/uniform-sizes', ['name' => 'XS'])->assertSessionHasErrors('name');

        $xs = UniformSize::where('name', 'XS')->firstOrFail();
        $this->actingAs($this->admin)->patch("/uniform-sizes/{$xs->id}/toggle-active");
        $this->assertFalse($xs->fresh()->is_active);

        foreach ([$this->staff, $this->reg] as $user) {
            $this->actingAs($user)->get('/uniform-sizes')->assertForbidden();
        }

        $this->seed(UniformSizeSeeder::class);
        $count = UniformSize::count();
        $this->seed(UniformSizeSeeder::class);
        $this->assertSame($count, UniformSize::count());
        $this->assertDatabaseHas('uniform_sizes', ['name' => 'XXXL']);
    }
}
