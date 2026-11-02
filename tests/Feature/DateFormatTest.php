<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\Applicant;
use App\Models\DiscountPeriod;
use App\Models\Region;
use App\Models\School;
use App\Models\User;
use App\Support\DateInput;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DateFormatTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year;
    private School $school;
    private User $reg;
    private User $admin;
    private User $clerk;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-12-05 10:00:00');

        $this->year = AcademicYear::create(['name' => '2026/2027', 'registration_fee' => 1500000, 'uniform_threshold_percent' => 60, 'is_active' => true]);
        foreach ([['33', 'JAWA TENGAH', 1, null], ['33.19', 'KAB. KUDUS', 2, '33'], ['33.19.01', 'KALIWUNGU', 3, '33.19'], ['33.19.01.2001', 'BAKALAN', 4, '33.19.01']] as [$c, $n, $l, $p]) {
            Region::create(['code' => $c, 'name' => $n, 'level' => $l, 'parent_code' => $p]);
        }
        $this->school = School::create(['name' => 'SMP 1', 'regency_code' => '33.19', 'regency_name' => 'KAB. KUDUS']);
        $this->reg = User::factory()->create();
        $this->admin = User::factory()->admin()->create();
        $this->clerk = User::factory()->role(Role::DaftarUlang)->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'registration_number' => 'A-001', 'name' => 'Ahmad Fauzi', 'gender' => 'L', 'birth_place' => 'Kudus', 'birth_date' => '25/12/2010',
            'province_code' => '33', 'regency_code' => '33.19', 'district_code' => '33.19.01', 'village_code' => '33.19.01.2001',
            'rt' => '3', 'rw' => '2', 'father_name' => 'Slamet', 'mother_name' => 'Siti Aminah', 'school_id' => $this->school->id,
        ], $override);
    }

    private function applicant(int $seq = 1, array $override = []): Applicant
    {
        return Applicant::create(array_merge([
            'academic_year_id' => $this->year->id,
            'registration_number' => Applicant::formatNumber($seq), 'registration_seq' => $seq,
            'name' => 'Murid '.$seq, 'gender' => 'P', 'birth_place' => 'Kudus', 'birth_date' => '2010-12-25',
            'province_code' => '33', 'province_name' => 'JAWA TENGAH', 'regency_code' => '33.19', 'regency_name' => 'KAB. KUDUS',
            'district_code' => '33.19.01', 'district_name' => 'KALIWUNGU', 'village_code' => '33.19.01.2001', 'village_name' => 'BAKALAN',
            'rt' => '001', 'rw' => '001', 'father_name' => 'Ayah', 'mother_name' => 'Ibu '.$seq, 'school_id' => $this->school->id,
            'person_key' => Applicant::personKey('Murid '.$seq, '2010-12-25', 'Ibu '.$seq), 'created_by' => $this->reg->id,
        ], $override));
    }

    // ---------- helper ----------

    public function test_date_input_helper(): void
    {
        $this->assertSame('2010-12-25', DateInput::toIso('25/12/2010'));
        $this->assertSame('2010-12-25', DateInput::toIso(' 25/12/2010 '));
        $this->assertSame('31/02/2010', DateInput::toIso('31/02/2010'));   // tidak valid: dikembalikan agar ditolak validasi
        $this->assertSame('25-12-2010', DateInput::toIso('25-12-2010'));
        $this->assertNull(DateInput::toIso(''));
        $this->assertNull(DateInput::toIso(null));

        $this->assertSame('2010-12-25', DateInput::iso('25/12/2010'));
        $this->assertSame('2010-12-25', DateInput::iso('2010-12-25'));
        $this->assertNull(DateInput::iso('31/02/2010'));
        $this->assertNull(DateInput::iso('abc'));
        $this->assertNull(DateInput::iso('25/12'));

        $this->assertSame('25/12/2010', DateInput::display('2010-12-25'));
        $this->assertSame('25/12/2010', DateInput::display('2010-12-25 08:00:00'));
        $this->assertSame('31/02/2010', DateInput::display('31/02/2010'));
        $this->assertSame('', DateInput::display(null));
    }

    // ---------- tanggal lahir ----------

    public function test_birth_date_is_entered_as_ddmmyyyy_and_stored_as_iso(): void
    {
        $this->actingAs($this->reg)->post('/applicants', $this->payload())->assertSessionHasNoErrors();

        $this->assertSame('2010-12-25', Applicant::firstOrFail()->birth_date->format('Y-m-d'));
    }

    public function test_birth_date_rejects_wrong_formats_with_a_clear_message(): void
    {
        foreach (['31/02/2010', '25-12-2010', '2010/12/25', '25/12/10', 'kemarin'] as $bad) {
            $this->actingAs($this->reg)->post('/applicants', $this->payload(['birth_date' => $bad]))
                ->assertSessionHasErrors(['birth_date' => 'Tanggal lahir harus berformat dd/mm/yyyy, misalnya 25/12/2010.']);
        }

        $this->actingAs($this->reg)->post('/applicants', $this->payload(['birth_date' => '06/12/2026']))
            ->assertSessionHasErrors(['birth_date' => 'Tanggal lahir tidak boleh melewati hari ini.']);
        $this->assertSame(0, Applicant::count());
    }

    public function test_forms_show_dates_as_ddmmyyyy_text_inputs(): void
    {
        $this->actingAs($this->reg)->get('/applicants/create')
            ->assertOk()->assertSee('dd/mm/yyyy')->assertDontSee('type="date"', false);

        $this->actingAs($this->reg)->post('/applicants', $this->payload());
        $applicant = Applicant::firstOrFail();

        $this->actingAs($this->reg)->get("/applicants/{$applicant->id}/edit")
            ->assertOk()->assertSee('value="25/12/2010"', false)->assertDontSee('2010-12-25');
        $this->actingAs($this->reg)->get("/applicants/{$applicant->id}")->assertOk()->assertSee('25/12/2010');
    }

    public function test_form_redisplays_a_wrong_date_as_typed_and_valid_one_as_ddmmyyyy(): void
    {
        $this->actingAs($this->reg)->from('/applicants/create')->post('/applicants', $this->payload(['birth_date' => '31/02/2010']));
        $this->get('/applicants/create')->assertSee('value="31/02/2010"', false);

        // tanggal valid tetapi ada kesalahan lain: tanggal tetap tampil dd/mm/yyyy
        $this->actingAs($this->reg)->from('/applicants/create')->post('/applicants', $this->payload(['name' => '']));
        $this->get('/applicants/create')->assertSee('value="25/12/2010"', false);
    }

    // ---------- pengunduran diri ----------

    public function test_withdrawal_date_uses_ddmmyyyy(): void
    {
        $a = $this->applicant();

        $this->actingAs($this->clerk)->post("/daftar-ulang/{$a->id}/mundur", ['date' => '2026/12/05', 'reason' => 'Pindah'])
            ->assertSessionHasErrors(['date' => 'Tanggal harus berformat dd/mm/yyyy, misalnya 25/12/2026.']);
        $this->actingAs($this->clerk)->post("/daftar-ulang/{$a->id}/mundur", ['date' => '06/12/2026', 'reason' => 'Pindah'])
            ->assertSessionHasErrors(['date' => 'Tanggal tidak boleh melewati hari ini.']);

        $this->actingAs($this->clerk)->post("/daftar-ulang/{$a->id}/mundur", ['date' => '05/12/2026', 'reason' => 'Pindah'])
            ->assertSessionHasNoErrors();
        $this->assertSame('2026-12-05', $a->fresh()->withdrawn_at->format('Y-m-d'));

        $this->actingAs($this->clerk)->get("/daftar-ulang/{$a->id}")->assertOk()->assertSee('05/12/2026');
    }

    public function test_withdrawal_form_defaults_to_today_in_ddmmyyyy(): void
    {
        $a = $this->applicant();

        $this->actingAs($this->clerk)->get("/daftar-ulang/{$a->id}")->assertOk()->assertSee('value="05/12/2026"', false);
    }

    // ---------- periode diskon ----------

    public function test_discount_period_dates_use_ddmmyyyy(): void
    {
        $this->actingAs($this->admin)->post('/discount-periods', [
            'name' => 'Gelombang 1', 'starts_on' => '01/11/2026', 'ends_on' => '30/11/2026', 'percent' => 10,
        ])->assertSessionHasNoErrors();

        $period = DiscountPeriod::firstOrFail();
        $this->assertSame('2026-11-01', $period->starts_on->format('Y-m-d'));
        $this->assertSame('2026-11-30', $period->ends_on->format('Y-m-d'));

        $this->actingAs($this->admin)->get("/discount-periods/{$period->id}/edit")
            ->assertOk()->assertSee('value="01/11/2026"', false)->assertSee('value="30/11/2026"', false);
        $this->actingAs($this->admin)->get('/discount-periods')->assertOk()->assertSee('01/11/2026')->assertSee('30/11/2026');
    }

    public function test_discount_period_rejects_wrong_dates(): void
    {
        $post = fn (string $start, string $end) => $this->actingAs($this->admin)->post('/discount-periods', [
            'name' => 'P', 'starts_on' => $start, 'ends_on' => $end, 'percent' => 10,
        ]);

        $post('30/02/2026', '30/11/2026')->assertSessionHasErrors(['starts_on' => 'Tanggal mulai harus berformat dd/mm/yyyy, misalnya 01/11/2026.']);
        $post('01/11/2026', '2026-13-01')->assertSessionHasErrors('ends_on');
        $post('30/11/2026', '01/11/2026')->assertSessionHasErrors(['ends_on' => 'Tanggal akhir tidak boleh sebelum tanggal mulai.']);
        $this->assertSame(0, DiscountPeriod::count());
    }

    // ---------- filter tanggal ----------

    public function test_applicant_list_date_filter_accepts_ddmmyyyy_and_ignores_garbage(): void
    {
        $today = $this->applicant(1, ['name' => 'Murid Hari Ini']);
        $old = $this->applicant(2, ['name' => 'Murid Kemarin']);
        DB::table('applicants')->where('id', $old->id)->update(['created_at' => '2026-12-04 09:00:00']);

        $this->actingAs($this->reg)->get('/applicants?date=05/12/2026')->assertSee('Murid Hari Ini')->assertDontSee('Murid Kemarin');
        $this->actingAs($this->reg)->get('/applicants?date=04/12/2026')->assertSee('Murid Kemarin')->assertDontSee('Murid Hari Ini');
        $this->actingAs($this->reg)->get('/applicants?date=abc')->assertSee('Murid Hari Ini')->assertSee('Murid Kemarin');
        $this->actingAs($this->reg)->get('/applicants?date=05/12')->assertSee('Murid Hari Ini')->assertSee('Murid Kemarin');
        $this->actingAs($this->reg)->get('/applicants?date=05/12/2026')->assertSee('value="05/12/2026"', false);
    }

    public function test_activity_log_date_filters_accept_ddmmyyyy(): void
    {
        $old = ActivityLog::record('test', 'Log lama');
        DB::table('activity_logs')->where('id', $old->id)->update(['created_at' => '2026-01-10 10:00:00']);
        ActivityLog::record('test', 'Log baru');

        $this->actingAs($this->admin)->get('/activity-logs?from=01/12/2026')->assertOk()->assertSee('Log baru')->assertDontSee('Log lama');
        $this->actingAs($this->admin)->get('/activity-logs?from=01/01/2026&to=31/01/2026')->assertOk()->assertSee('Log lama')->assertDontSee('Log baru');
        $this->actingAs($this->admin)->get('/activity-logs?from=bukan-tanggal')->assertOk()->assertSee('Log lama')->assertSee('Log baru');
    }

    public function test_no_html_date_pickers_remain(): void
    {
        $a = $this->applicant();

        foreach (['/applicants', '/applicants/create', "/applicants/{$a->id}/edit", '/activity-logs', '/discount-periods/create'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk()->assertDontSee('type="date"', false);
        }
        $this->actingAs($this->clerk)->get("/daftar-ulang/{$a->id}")->assertOk()->assertDontSee('type="date"', false);
    }
}
