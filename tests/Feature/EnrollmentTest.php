<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus as E;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\ApplicantBilling;
use App\Models\DiscountPeriod;
use App\Models\Payment;
use App\Models\Region;
use App\Models\School;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\EnrollmentStats;
use App\Services\Terbilang;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year;
    private School $school;
    private User $clerk;
    private User $admin;
    private User $reg;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-12-05 10:00:00'); // di luar periode diskon mana pun, kecuali tes yang mengubahnya

        $this->year = AcademicYear::create(['name' => '2026/2027', 'registration_fee' => 1500000, 'uniform_threshold_percent' => 60, 'is_active' => true]);
        $this->school = School::create(['name' => 'SMP 1', 'regency_code' => '33.19', 'regency_name' => 'KAB. KUDUS']);
        $this->clerk = User::factory()->role(Role::DaftarUlang)->create();
        $this->admin = User::factory()->admin()->create();
        $this->reg = User::factory()->create(); // panitia pendaftaran
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
            'rt' => '001', 'rw' => '001', 'father_name' => 'Ayah '.$seq, 'mother_name' => 'Ibu '.$seq,
            'school_id' => $this->school->id,
            'person_key' => Applicant::personKey('Murid '.$seq, '2010-01-01', 'Ibu '.$seq),
            'created_by' => $this->reg->id,
        ], $override));
    }

    private function pay(Applicant $a, int|string $amount, ?User $as = null, array $extra = [])
    {
        return $this->actingAs($as ?? $this->clerk)->post("/daftar-ulang/{$a->id}/pembayaran", ['amount' => $amount] + $extra);
    }

    private function svc(): EnrollmentService
    {
        return app(EnrollmentService::class);
    }

    private function summary(Applicant $a): array
    {
        return $this->svc()->summary($a->fresh());
    }

    private function period(array $override = []): DiscountPeriod
    {
        return DiscountPeriod::create(array_merge([
            'academic_year_id' => $this->year->id, 'name' => 'Gelombang 1',
            'starts_on' => '2026-11-01', 'ends_on' => '2026-11-30', 'percent' => 20, 'is_active' => true,
        ], $override));
    }

    // ---------- terbilang dan pembulatan ----------

    public function test_terbilang(): void
    {
        foreach ([
            0 => 'nol', 1 => 'satu', 11 => 'sebelas', 15 => 'lima belas', 100 => 'seratus', 101 => 'seratus satu',
            215 => 'dua ratus lima belas', 1000 => 'seribu', 1001 => 'seribu satu', 2000 => 'dua ribu',
            500000 => 'lima ratus ribu', 1500000 => 'satu juta lima ratus ribu', 12000000 => 'dua belas juta',
        ] as $n => $words) {
            $this->assertSame($words, Terbilang::make($n), "angka {$n}");
        }
        $this->assertSame('seribu rupiah', Terbilang::rupiah(1000));
    }

    public function test_net_bill_rounds_down(): void
    {
        $this->assertSame(1200000, EnrollmentService::netBill(1500000, 20));
        $this->assertSame(1005000, EnrollmentService::netBill(1500001, 33));
        $this->assertSame(1500000, EnrollmentService::netBill(1500000, 0));
    }

    // ---------- pembayaran ----------

    public function test_installments_move_status_from_terdaftar_to_menyicil_to_lunas(): void
    {
        $a = $this->applicant();
        $this->assertSame(E::Terdaftar, $this->summary($a)['status']);

        $this->pay($a, 500000)->assertSessionHasNoErrors()->assertSessionHas('success');
        $first = Payment::firstOrFail();
        $this->assertSame('KW-2026-00001', $first->receipt_number);
        $this->assertSame($this->clerk->id, $first->received_by);
        $this->assertSame(E::Menyicil, $this->summary($a)['status']);
        $this->assertSame(1000000, $this->summary($a)['remaining']);

        $this->pay($a, 1000000)->assertSessionHasNoErrors();
        $this->assertSame('KW-2026-00002', Payment::orderByDesc('id')->first()->receipt_number);
        $this->assertSame(E::Lunas, $this->summary($a)['status']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'payment.created', 'subject_id' => $a->id]);
    }

    public function test_payment_validation(): void
    {
        $a = $this->applicant();

        $this->pay($a, 0)->assertSessionHasErrors('amount');
        $this->pay($a, 'abc')->assertSessionHasErrors('amount');
        $this->pay($a, 2000000)->assertSessionHas('error', fn ($m) => str_contains($m, 'melebihi'));
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, ApplicantBilling::count()); // pembayaran gagal tidak meninggalkan tagihan beku

        $this->pay($a, '1.500.000')->assertSessionHasNoErrors();
        $this->assertSame(E::Lunas, $this->summary($a)['status']);
        $this->pay($a, 1)->assertSessionHas('error', fn ($m) => str_contains($m, 'lunas'));
    }

    public function test_payment_is_refused_when_fee_not_set(): void
    {
        $this->year->update(['registration_fee' => 0]);
        $a = $this->applicant();

        $this->pay($a, 100000)->assertSessionHas('error', fn ($m) => str_contains($m, 'belum diatur'));
        $this->assertSame(0, Payment::count());
    }

    public function test_receipt_numbers_are_sequential_per_year(): void
    {
        $a = $this->applicant(1);
        $b = $this->applicant(2);

        $this->pay($a, 100000);
        $this->pay($b, 100000);
        $this->pay($a, 100000);

        $this->assertSame(['KW-2026-00001', 'KW-2026-00002', 'KW-2026-00003'], Payment::orderBy('id')->pluck('receipt_number')->all());
    }

    // ---------- diskon periode ----------

    public function test_period_discount_is_applied_at_first_payment_and_frozen(): void
    {
        Carbon::setTestNow('2026-11-10 10:00:00');
        $period = $this->period();
        $a = $this->applicant();

        $this->pay($a, 100000)->assertSessionHasNoErrors();

        $billing = ApplicantBilling::firstOrFail();
        $this->assertSame(20, $billing->discount_percent);
        $this->assertSame('periode', $billing->discount_type);
        $this->assertSame(1500000, $billing->fee_snapshot);
        $this->assertSame(1200000, $billing->net_bill);
        $this->assertDatabaseHas('activity_logs', ['event' => 'discount.applied', 'subject_id' => $a->id]);

        // admin mengubah periode dan biaya: murid yang sudah ditandai tidak berubah
        $this->actingAs($this->admin)->put("/discount-periods/{$period->id}", [
            'name' => 'Gelombang 1', 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-30', 'percent' => 50,
        ])->assertSessionHasNoErrors();
        $this->year->update(['registration_fee' => 2000000]);

        $this->pay($a, 1100000)->assertSessionHasNoErrors();
        $this->assertSame(1200000, $billing->fresh()->net_bill);
        $this->assertSame(E::Lunas, $this->summary($a)['status']);
    }

    public function test_no_discount_outside_period_and_boundaries_are_inclusive(): void
    {
        $this->period();

        Carbon::setTestNow('2026-12-05 10:00:00');
        $a = $this->applicant(1);
        $this->pay($a, 100000);
        $this->assertSame(0, $a->fresh()->billing->discount_percent);
        $this->assertSame('none', $a->fresh()->billing->discount_type);

        Carbon::setTestNow('2026-11-30 23:30:00');
        $b = $this->applicant(2);
        $this->pay($b, 100000);
        $this->assertSame(20, $b->fresh()->billing->discount_percent);

        Carbon::setTestNow('2026-11-01 00:10:00');
        $c = $this->applicant(3);
        $this->pay($c, 100000);
        $this->assertSame(20, $c->fresh()->billing->discount_percent);
    }

    public function test_inactive_period_gives_no_discount(): void
    {
        Carbon::setTestNow('2026-11-10 10:00:00');
        $this->period(['is_active' => false]);
        $a = $this->applicant();

        $this->pay($a, 100000);
        $this->assertSame(0, $a->fresh()->billing->discount_percent);
    }

    public function test_cancelling_first_payment_returns_to_terdaftar_and_removes_period_discount(): void
    {
        Carbon::setTestNow('2026-11-10 10:00:00');
        $this->period();
        $a = $this->applicant();
        $this->pay($a, 100000);
        $payment = Payment::firstOrFail();

        $this->actingAs($this->admin)->post("/pembayaran/{$payment->id}/batal", ['reason' => 'salah input'])->assertSessionHasNoErrors();

        $this->assertSame(0, ApplicantBilling::count());
        $this->assertSame(E::Terdaftar, $this->summary($a)['status']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'payment.cancelled', 'reason' => 'salah input']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'discount.removed', 'subject_id' => $a->id]);

        // pembayaran berikutnya dinilai ulang sebagai pembayaran pertama yang baru
        Carbon::setTestNow('2026-12-05 10:00:00');
        $this->pay($a, 100000);
        $this->assertSame(0, $a->fresh()->billing->discount_percent);
        $this->assertSame('KW-2026-00002', Payment::orderByDesc('id')->first()->receipt_number); // nomor kwitansi tidak dipakai ulang
    }

    public function test_cancelling_one_of_several_payments_keeps_the_frozen_billing(): void
    {
        Carbon::setTestNow('2026-11-10 10:00:00');
        $this->period();
        $a = $this->applicant();
        $this->pay($a, 100000);
        $this->pay($a, 200000);
        $first = Payment::orderBy('id')->first();

        $this->actingAs($this->admin)->post("/pembayaran/{$first->id}/batal", ['reason' => 'salah'])->assertSessionHasNoErrors();

        $this->assertSame(1200000, $a->fresh()->billing->net_bill);
        $this->assertSame(200000, $this->summary($a)['paid']);
        $this->assertSame(E::Menyicil, $this->summary($a)['status']);
    }

    public function test_cancel_rules(): void
    {
        $a = $this->applicant();
        $this->pay($a, 100000);
        $payment = Payment::firstOrFail();

        $this->actingAs($this->clerk)->post("/pembayaran/{$payment->id}/batal", ['reason' => 'x salah'])->assertForbidden();
        $this->actingAs($this->reg)->post("/pembayaran/{$payment->id}/batal", ['reason' => 'x salah'])->assertForbidden();
        $this->actingAs($this->admin)->post("/pembayaran/{$payment->id}/batal", [])->assertSessionHasErrors('reason');
        $this->assertNull($payment->fresh()->cancelled_at);

        $this->actingAs($this->admin)->post("/pembayaran/{$payment->id}/batal", ['reason' => 'salah input']);
        $this->assertNotNull($payment->fresh()->cancelled_at);
        $this->assertSame($this->admin->id, $payment->fresh()->cancelled_by);

        $this->actingAs($this->admin)->post("/pembayaran/{$payment->id}/batal", ['reason' => 'sekali lagi'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'sudah dibatalkan'));
    }

    // ---------- periode diskon (admin) ----------

    public function test_period_validation_overlap_and_permissions(): void
    {
        $data = fn (string $start, string $end, int $percent = 10) => ['name' => 'P', 'starts_on' => $start, 'ends_on' => $end, 'percent' => $percent];

        $this->actingAs($this->admin)->post('/discount-periods', $data('2026-11-01', '2026-11-15'))->assertSessionHasNoErrors();
        $this->assertSame(1, DiscountPeriod::count());

        $this->actingAs($this->admin)->post('/discount-periods', $data('2026-11-10', '2026-11-20'))->assertSessionHasErrors('starts_on');
        $this->actingAs($this->admin)->post('/discount-periods', $data('2026-11-16', '2026-11-30'))->assertSessionHasNoErrors();
        $this->assertSame(2, DiscountPeriod::count());

        $this->actingAs($this->admin)->post('/discount-periods', $data('2026-12-01', '2026-11-01'))->assertSessionHasErrors('ends_on');
        $this->actingAs($this->admin)->post('/discount-periods', $data('2026-12-01', '2026-12-10', 0))->assertSessionHasErrors('percent');
        $this->actingAs($this->admin)->post('/discount-periods', $data('2026-12-01', '2026-12-10', 100))->assertSessionHasErrors('percent');

        foreach ([$this->clerk, $this->reg] as $user) {
            $this->actingAs($user)->get('/discount-periods')->assertForbidden();
            $this->actingAs($user)->post('/discount-periods', $data('2027-01-01', '2027-01-10'))->assertForbidden();
        }
    }

    public function test_inactive_period_may_overlap_but_cannot_be_reactivated_into_overlap(): void
    {
        $first = $this->period(['starts_on' => '2026-11-01', 'ends_on' => '2026-11-15']);
        $this->actingAs($this->admin)->patch("/discount-periods/{$first->id}/toggle-active")->assertSessionHas('success');
        $this->assertFalse($first->fresh()->is_active);

        $this->actingAs($this->admin)->post('/discount-periods', ['name' => 'B', 'starts_on' => '2026-11-05', 'ends_on' => '2026-11-12', 'percent' => 15])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->patch("/discount-periods/{$first->id}/toggle-active")->assertSessionHas('error');
        $this->assertFalse($first->fresh()->is_active);
    }

    // ---------- diskon manual ----------

    public function test_manual_discount_replaces_period_discount_and_recalculates_status(): void
    {
        Carbon::setTestNow('2026-11-10 10:00:00');
        $this->period();
        $a = $this->applicant();
        $this->pay($a, 500000);

        $this->actingAs($this->admin)->post("/daftar-ulang/{$a->id}/diskon", ['percent' => 40, 'reason' => 'Anak yatim'])->assertSessionHasNoErrors();

        $billing = $a->fresh()->billing;
        $this->assertSame('manual', $billing->discount_type);
        $this->assertSame(900000, $billing->net_bill);
        $this->assertSame('Anak yatim', $billing->reason);
        $this->assertDatabaseHas('activity_logs', ['event' => 'discount.manual_set', 'reason' => 'Anak yatim']);

        $this->pay($a, 400000);
        $this->assertSame(E::Lunas, $this->summary($a)['status']);
    }

    public function test_manual_discount_rules(): void
    {
        $a = $this->applicant();

        $this->actingAs($this->admin)->post("/daftar-ulang/{$a->id}/diskon", ['percent' => 100, 'reason' => 'gratis'])->assertSessionHasErrors('percent');
        $this->actingAs($this->admin)->post("/daftar-ulang/{$a->id}/diskon", ['percent' => 10])->assertSessionHasErrors('reason');
        $this->actingAs($this->clerk)->post("/daftar-ulang/{$a->id}/diskon", ['percent' => 10, 'reason' => 'abc'])->assertForbidden();
        $this->assertSame(0, ApplicantBilling::count());
    }

    public function test_manual_discount_before_first_payment_survives_period_and_keeps_status_terdaftar(): void
    {
        $a = $this->applicant();
        $this->actingAs($this->admin)->post("/daftar-ulang/{$a->id}/diskon", ['percent' => 30, 'reason' => 'Keringanan'])->assertSessionHasNoErrors();
        $this->assertSame(E::Terdaftar, $this->summary($a)['status']);
        $this->assertSame(1050000, $a->fresh()->billing->net_bill);

        Carbon::setTestNow('2026-11-10 10:00:00');
        $this->period();
        $this->pay($a, 100000);
        $this->assertSame(30, $a->fresh()->billing->discount_percent); // tidak dinilai ulang oleh periode

        // pembayaran pertama dibatalkan: diskon manual tetap ada
        $this->actingAs($this->admin)->post('/pembayaran/'.Payment::first()->id.'/batal', ['reason' => 'salah']);
        $this->assertSame(E::Terdaftar, $this->summary($a)['status']);
        $this->assertSame(1, ApplicantBilling::count());
    }

    public function test_overpayment_is_flagged_when_discount_lowers_the_bill(): void
    {
        $a = $this->applicant();
        $this->pay($a, 1000000);
        $this->actingAs($this->admin)->post("/daftar-ulang/{$a->id}/diskon", ['percent' => 50, 'reason' => 'Penyesuaian']);

        $s = $this->summary($a);
        $this->assertSame(750000, $s['net']);
        $this->assertSame(250000, $s['overpaid']);
        $this->assertSame(E::Lunas, $s['status']);
        $this->actingAs($this->admin)->get("/daftar-ulang/{$a->id}")->assertOk()->assertSee('Kelebihan bayar');
    }

    // ---------- pengunduran diri ----------

    public function test_clerk_can_withdraw_unpaid_applicant_and_payments_are_then_blocked(): void
    {
        $a = $this->applicant();

        $this->actingAs($this->clerk)->post("/daftar-ulang/{$a->id}/mundur", ['date' => '2026-12-05', 'reason' => 'Pindah sekolah'])
            ->assertSessionHasNoErrors();

        $this->assertSame(E::Mundur, $this->summary($a)['status']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'enrollment.withdrawn', 'reason' => 'Pindah sekolah']);

        $this->pay($a, 100000)->assertSessionHas('error', fn ($m) => str_contains($m, 'mengundurkan diri'));
        $this->assertSame(0, Payment::count());
    }

    public function test_only_admin_can_withdraw_a_paid_applicant_and_cancel_withdrawal(): void
    {
        $a = $this->applicant();
        $this->pay($a, 500000);

        $this->actingAs($this->clerk)->post("/daftar-ulang/{$a->id}/mundur", ['date' => '2026-12-05', 'reason' => 'Pindah'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Hanya admin'));
        $this->assertNull($a->fresh()->withdrawn_at);

        $this->actingAs($this->admin)->post("/daftar-ulang/{$a->id}/mundur", ['date' => '2026-12-05', 'reason' => 'Pindah'])->assertSessionHasNoErrors();
        $this->assertSame(E::Mundur, $this->summary($a)['status']);
        $this->assertSame(500000, $this->summary($a)['paid']); // riwayat pembayaran tidak berubah

        $this->actingAs($this->clerk)->post("/daftar-ulang/{$a->id}/batal-mundur", ['reason' => 'ternyata jadi'])->assertForbidden();
        $this->actingAs($this->admin)->post("/daftar-ulang/{$a->id}/batal-mundur", ['reason' => 'ternyata jadi'])->assertSessionHasNoErrors();
        $this->assertSame(E::Menyicil, $this->summary($a)['status']);
    }

    public function test_withdrawal_date_and_reason_are_validated(): void
    {
        $a = $this->applicant();

        $this->actingAs($this->clerk)->post("/daftar-ulang/{$a->id}/mundur", ['date' => '2027-01-01', 'reason' => 'abc'])->assertSessionHasErrors('date');
        $this->actingAs($this->clerk)->post("/daftar-ulang/{$a->id}/mundur", ['date' => '2026-12-05'])->assertSessionHasErrors('reason');
    }

    public function test_refund_note_is_admin_only_and_does_not_change_amounts(): void
    {
        $a = $this->applicant();
        $this->pay($a, 500000);
        $this->actingAs($this->admin)->post("/daftar-ulang/{$a->id}/mundur", ['date' => '2026-12-05', 'reason' => 'Pindah']);

        $this->actingAs($this->clerk)->put("/daftar-ulang/{$a->id}/catatan-pengembalian", ['refund_note' => 'x'])->assertForbidden();
        $this->actingAs($this->admin)->put("/daftar-ulang/{$a->id}/catatan-pengembalian", ['refund_note' => 'Dikembalikan Rp 500.000 tgl 6 Des'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Dikembalikan Rp 500.000 tgl 6 Des', $a->fresh()->refund_note);
        $this->assertSame(500000, $this->summary($a)['paid']);
    }

    // ---------- penguncian dan penghapusan ----------

    public function test_referrer_is_locked_after_first_valid_payment_for_registration_staff(): void
    {
        foreach ([['33', 'JAWA TENGAH', 1, null], ['33.19', 'KAB. KUDUS', 2, '33'], ['33.19.01', 'KALIWUNGU', 3, '33.19'], ['33.19.01.2001', 'BAKALAN', 4, '33.19.01']] as [$c, $n, $l, $p]) {
            Region::create(['code' => $c, 'name' => $n, 'level' => $l, 'parent_code' => $p]);
        }
        $one = ['referrer_name' => 'Budi Satu', 'referrer_class_name' => 'XI A'];
        $two = ['referrer_name' => 'Siti Dua', 'referrer_class_name' => 'XI B'];
        $a = $this->applicant(1, $one);

        $payload = fn (array $referrer) => [
            'registration_number' => 'A-001', 'name' => 'Murid 1', 'gender' => 'P', 'birth_place' => 'Kudus', 'birth_date' => '2010-01-01',
            'province_code' => '33', 'regency_code' => '33.19', 'district_code' => '33.19.01', 'village_code' => '33.19.01.2001',
            'rt' => '1', 'rw' => '1', 'father_name' => 'Ayah 1', 'mother_name' => 'Ibu 1',
            'school_id' => $this->school->id,
        ] + $referrer;

        // belum ada pembayaran: boleh diubah
        $this->actingAs($this->reg)->put("/applicants/{$a->id}", $payload($two))->assertSessionHasNoErrors();
        $this->assertSame($two['referrer_name'], $a->fresh()->referrer_name);
        $this->assertSame($two['referrer_class_name'], $a->fresh()->referrer_class_name);
        $this->assertFalse($a->fresh()->referrerLocked());

        $this->pay($a, 100000);
        $this->assertTrue($a->fresh()->referrerLocked());

        $this->actingAs($this->reg)->put("/applicants/{$a->id}", $payload($one))->assertSessionHasErrors('referrer_name');
        $this->assertSame($two['referrer_name'], $a->fresh()->referrer_name);

        $this->actingAs($this->admin)->put("/applicants/{$a->id}", $payload($one))->assertSessionHasNoErrors();
        $this->assertSame($one['referrer_name'], $a->fresh()->referrer_name);

        // pembayaran dibatalkan: kunci terlepas
        $this->actingAs($this->admin)->post('/pembayaran/'.Payment::first()->id.'/batal', ['reason' => 'salah']);
        $this->assertFalse($a->fresh()->referrerLocked());
    }

    public function test_applicant_with_valid_payment_cannot_be_deleted(): void
    {
        $a = $this->applicant();
        $this->pay($a, 100000);

        $this->actingAs($this->admin)->delete("/applicants/{$a->id}", ['reason' => 'salah input'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'pembayaran sah'));
        $this->assertNotSoftDeleted($a);

        $this->actingAs($this->admin)->post('/pembayaran/'.Payment::first()->id.'/batal', ['reason' => 'salah']);
        $this->actingAs($this->admin)->delete("/applicants/{$a->id}", ['reason' => 'salah input']);
        $this->assertSoftDeleted($a);
    }

    // ---------- daftar, akses, cetak, dashboard ----------

    public function test_index_filters_by_status(): void
    {
        $registered = $this->applicant(1, ['name' => 'Murid Terdaftar']);
        $partial = $this->applicant(2, ['name' => 'Murid Menyicil']);
        $full = $this->applicant(3, ['name' => 'Murid Lunas']);
        $gone = $this->applicant(4, ['name' => 'Murid Mundur']);
        $this->pay($partial, 500000);
        $this->pay($full, 1500000);
        $this->actingAs($this->admin)->post("/daftar-ulang/{$gone->id}/mundur", ['date' => '2026-12-05', 'reason' => 'Pindah']);

        $names = ['terdaftar' => 'Murid Terdaftar', 'menyicil' => 'Murid Menyicil', 'lunas' => 'Murid Lunas', 'mundur' => 'Murid Mundur'];
        foreach ($names as $status => $name) {
            $response = $this->actingAs($this->clerk)->get("/daftar-ulang?status={$status}")->assertOk()->assertSee($name);
            foreach (array_diff($names, [$name]) as $other) {
                $response->assertDontSee($other);
            }
        }
        $this->actingAs($this->clerk)->get('/daftar-ulang')->assertSee('Murid Terdaftar')->assertSee('Murid Lunas');
    }

    public function test_access_by_role(): void
    {
        $a = $this->applicant();

        foreach ([$this->reg, User::factory()->role(Role::Seragam)->create()] as $user) {
            $this->actingAs($user)->get('/daftar-ulang')->assertForbidden();
            $this->actingAs($user)->get("/daftar-ulang/{$a->id}")->assertForbidden();
            $this->actingAs($user)->post("/daftar-ulang/{$a->id}/pembayaran", ['amount' => 1000])->assertForbidden();
        }
        foreach ([$this->clerk, $this->admin] as $user) {
            $this->actingAs($user)->get('/daftar-ulang')->assertOk();
            $this->actingAs($user)->get("/daftar-ulang/{$a->id}")->assertOk();
        }
    }

    public function test_registration_staff_sees_payment_status_and_amounts_read_only(): void
    {
        $a = $this->applicant();
        $this->pay($a, 500000);

        $this->actingAs($this->reg)->get("/applicants/{$a->id}")
            ->assertOk()->assertSee('Daftar ulang dan pembayaran')->assertSee('Rp 500.000')->assertSee('Daftar ulang, menyicil');
    }

    public function test_receipt_and_proof_pages(): void
    {
        config(['sekolah.name' => 'SMA CONTOH 1']);
        $a = $this->applicant();
        $this->pay($a, 500000);
        $payment = Payment::firstOrFail();

        $this->actingAs($this->clerk)->get("/kwitansi/{$payment->id}")
            ->assertOk()->assertSee('KW-2026-00001')->assertSee('Lima ratus ribu rupiah')->assertSee('SMA CONTOH 1')->assertDontSee('DIBATALKAN');
        $this->actingAs($this->clerk)->get("/daftar-ulang/{$a->id}/bukti")
            ->assertOk()->assertSee('Bukti Daftar Ulang')->assertSee('KW-2026-00001');

        $this->actingAs($this->admin)->post("/pembayaran/{$payment->id}/batal", ['reason' => 'salah']);
        $this->actingAs($this->clerk)->get("/kwitansi/{$payment->id}")->assertSee('DIBATALKAN');
        $this->actingAs($this->reg)->get("/kwitansi/{$payment->id}")->assertForbidden();
    }

    public function test_dashboards(): void
    {
        $a = $this->applicant();
        $this->pay($a, 500000);

        $this->actingAs($this->clerk)->get('/dashboard')->assertOk()->assertSee('Uang masuk hari ini')->assertSee('Rp 500.000');
        $this->actingAs($this->admin)->get('/dashboard')->assertOk()->assertSee('Tagihan bersih')->assertSee('Total diterima')
            ->assertSee('Status pendaftar')->assertSee('Jumlah pendaftar per asal sekolah')
            ->assertSee('Pendaftar')->assertSee('Daftar ulang')->assertSee('Cabut')
            ->assertSee('Diagram status pendaftar', false);
        $this->actingAs($this->reg)->get('/dashboard')->assertOk()->assertDontSee('Uang masuk hari ini')
            ->assertDontSee('Jumlah pendaftar per asal sekolah');
    }

    public function test_admin_dashboard_charts_show_registration_enrollment_withdrawal_and_school_counts(): void
    {
        $registered = $this->applicant(1, ['name' => 'Terdaftar']);
        $installment = $this->applicant(2, ['name' => 'Menyicil']);
        $paid = $this->applicant(3, ['name' => 'Lunas']);
        $withdrawn = $this->applicant(4, ['name' => 'Cabut']);
        $school2 = School::create(['name' => 'SMP 2', 'regency_code' => '33.20', 'regency_name' => 'KAB. JEPARA']);
        $withdrawn->update(['school_id' => $school2->id]);
        $this->pay($installment, 500000);
        $this->pay($paid, 1500000);
        $this->pay($withdrawn, 500000);
        $this->actingAs($this->admin)->post("/daftar-ulang/{$withdrawn->id}/mundur", ['date' => '2026-12-05', 'reason' => 'Pindah']);

        $response = $this->actingAs($this->admin)->get('/dashboard');
        $response->assertOk()
            ->assertSee('SMP 1')->assertSee('SMP 2')
            ->assertSee('aria-label="Diagram status pendaftar, total 4"', false);
        $this->assertSame([
            ['label' => 'Pendaftar', 'count' => 1, 'color' => '#64748b'],
            ['label' => 'Daftar ulang', 'count' => 2, 'color' => '#16a34a'],
            ['label' => 'Cabut', 'count' => 1, 'color' => '#dc2626'],
        ], $response->viewData('charts')['status']);
        $this->assertSame([
            ['name' => 'SMP 1', 'count' => 3],
            ['name' => 'SMP 2', 'count' => 1],
        ], $response->viewData('charts')['schools']);

        $response = $this->actingAs($this->reg)->get('/dashboard');
        $response->assertOk()
            ->assertDontSee('Diagram status pendaftar', false)
            ->assertDontSee('Jumlah pendaftar per asal sekolah');
        $this->assertNull($response->viewData('charts'));
    }

    public function test_stats_service(): void
    {
        $full = $this->applicant(1);
        $partial = $this->applicant(2);
        $this->applicant(3); // terdaftar
        $gone = $this->applicant(4);
        $this->pay($full, 1500000);
        $this->pay($partial, 500000);
        $this->pay($gone, 500000);
        $this->actingAs($this->admin)->post("/daftar-ulang/{$gone->id}/mundur", ['date' => '2026-12-05', 'reason' => 'Pindah']);

        $stats = app(EnrollmentStats::class)->forYear($this->year);

        $this->assertSame(['terdaftar' => 1, 'menyicil' => 1, 'lunas' => 1, 'mundur' => 1], $stats['counts']);
        $this->assertSame(3000000, $stats['money']['gross']);
        $this->assertSame(2000000, $stats['money']['paid']);
        $this->assertSame(1000000, $stats['money']['remaining']);
        $this->assertSame(1000000, $stats['money']['installment_remaining']);
        $this->assertSame(500000, $stats['money']['withdrawn_paid']);
        $this->assertSame(1, $stats['withdrawn_with_payment']);
        $this->assertSame(2500000, $stats['today_money']);
        $this->assertSame(3, $stats['today_students']);
    }

    public function test_archived_year_is_read_only(): void
    {
        $a = $this->applicant();
        $this->pay($a, 100000);
        $this->year->update(['is_active' => false]);

        $this->pay($a, 100000)->assertSessionHas('error', fn ($m) => str_contains($m, 'arsip'));
        $this->actingAs($this->clerk)->post("/daftar-ulang/{$a->id}/mundur", ['date' => '2026-12-05', 'reason' => 'abc'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'arsip'));
        $this->actingAs($this->admin)->post('/pembayaran/'.Payment::first()->id.'/batal', ['reason' => 'abc'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'arsip'));
        $this->actingAs($this->clerk)->get("/daftar-ulang/{$a->id}")->assertOk()->assertSee('Tahun ajaran arsip');
        $this->assertSame(1, Payment::count());
    }
}
