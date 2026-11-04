<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\ApplicantFamilyDetail;
use App\Models\DiscountPeriod;
use App\Models\Payment;
use App\Models\School;
use App\Models\UniformSize;
use App\Models\UniformType;
use App\Models\Occupation;
use App\Models\User;
use App\Reports\Report;
use App\Reports\ReportBuilder;
use App\Services\EnrollmentService;
use App\Services\UniformService;
use App\Support\XlsxWriter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year;
    private School $school;
    private School $school2;
    private User $admin;
    private User $reg;
    private User $clerk;
    private User $staff;
    private string $refX;
    private string $refY;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-12-05 10:00:00');

        $this->year = AcademicYear::create(['name' => '2026/2027', 'registration_fee' => 1500000, 'uniform_threshold_percent' => 60, 'is_active' => true]);
        $this->school = School::create(['name' => 'SMP 1', 'regency_code' => '33.19', 'regency_name' => 'KAB. KUDUS']);
        $this->school2 = School::create(['name' => 'SMP 2', 'regency_code' => '33.20', 'regency_name' => 'KAB. JEPARA']);
        $this->admin = User::factory()->admin()->create();
        $this->reg = User::factory()->create();
        $this->clerk = User::factory()->role(Role::DaftarUlang)->create();
        $this->staff = User::factory()->role(Role::Seragam)->create();
        $this->refX = 'Budi Santoso';
        $this->refY = 'Siti Aminah';
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---------- bantuan ----------

    private function applicant(int $seq, array $override = []): Applicant
    {
        return Applicant::create(array_merge([
            'academic_year_id' => $this->year->id,
            'registration_number' => Applicant::formatNumber($seq), 'registration_seq' => $seq,
            'name' => 'Murid '.$seq, 'gender' => 'P', 'birth_place' => 'Kudus', 'birth_date' => '2010-12-25',
            'province_code' => '33', 'province_name' => 'JAWA TENGAH', 'regency_code' => '33.19', 'regency_name' => 'KAB. KUDUS',
            'district_code' => '33.19.01', 'district_name' => 'KALIWUNGU', 'village_code' => '33.19.01.2001', 'village_name' => 'BAKALAN',
            'rt' => '001', 'rw' => '001', 'father_name' => 'Ayah '.$seq, 'mother_name' => 'Ibu '.$seq, 'phone' => '081234567890',
            'school_id' => $this->school->id,
            'person_key' => Applicant::personKey('Murid '.$seq, '2010-12-25', 'Ibu '.$seq), 'created_by' => $this->reg->id,
        ], $override));
    }

    private function pay(Applicant $a, int $amount, ?User $by = null): Payment
    {
        return app(EnrollmentService::class)->addPayment($a, $amount, null, $by ?? $this->clerk);
    }

    private function build(string $key, array $filters = [], ?User $user = null): Report
    {
        return app(ReportBuilder::class)->build($key, $this->year, $filters, $user ?? $this->admin);
    }

    private function val(mixed $cell): mixed
    {
        return is_array($cell) ? ($cell['v'] ?? null) : $cell;
    }

    /** @return list<list<mixed>> */
    private function rows(Report $report, int $section = 0): array
    {
        return array_map(fn ($row) => array_map(fn ($c) => $this->val($c), $row), $report->sections[$section]['rows']);
    }

    private function footer(Report $report, int $section = 0): array
    {
        return array_map(fn ($c) => $this->val($c), $report->sections[$section]['footer']);
    }

    private function names(Report $report, int $nameColumn, int $section = 0): array
    {
        return array_column($this->rows($report, $section), $nameColumn);
    }

    /** Skenario daftar ulang: A menyicil, B lunas, C belum bayar, D mundur setelah bayar, E mundur belum bayar. */
    private function enrollmentScenario(): array
    {
        $a = $this->applicant(1, ['name' => 'Murid A', 'referrer_name' => $this->refX, 'referrer_class_name' => 'XI A']);
        $b = $this->applicant(2, ['name' => 'Murid B', 'referrer_name' => $this->refX, 'referrer_class_name' => 'XI A']);
        $c = $this->applicant(3, ['name' => 'Murid C', 'referrer_name' => $this->refX, 'referrer_class_name' => 'XI A']);
        $d = $this->applicant(4, ['name' => 'Murid D', 'referrer_name' => $this->refX, 'referrer_class_name' => 'XI A']);
        $e = $this->applicant(5, ['name' => 'Murid E', 'referrer_name' => $this->refX, 'referrer_class_name' => 'XI A']);
        $f = $this->applicant(6, ['name' => 'Murid F', 'referrer_name' => $this->refY, 'referrer_class_name' => 'XI B']);
        $g = $this->applicant(7, ['name' => 'Murid G']);

        $this->pay($a, 500000);
        $this->pay($b, 1500000);
        $this->pay($d, 500000);
        $this->pay($f, 100000);
        app(EnrollmentService::class)->withdraw($d, '2026-12-05', 'Pindah', $this->admin);
        app(EnrollmentService::class)->withdraw($e, '2026-12-05', 'Pindah', $this->admin);

        return compact('a', 'b', 'c', 'd', 'e', 'f', 'g');
    }

    private function xlsxParts(string $binary): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xt');
        file_put_contents($path, $binary);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true, 'berkas bukan zip yang valid');

        $parts = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $parts[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
        }
        $zip->close();
        @unlink($path);

        return $parts;
    }

    // ---------- akses ----------

    public function test_index_lists_only_reports_allowed_for_the_role(): void
    {
        $this->actingAs($this->admin)->get('/laporan')->assertOk()
            ->assertSee('Rekap Reward Pembawa')->assertSee('Daftar Pendaftar')->assertSee('Rekap Data Lengkap Siswa')->assertSee('Rekap Siswa Yatim')->assertSee('Rekap Siswa Berprestasi')->assertSee('Rekap Pembayaran Harian')->assertSee('Rekap Penyerahan Seragam')
            ->assertDontSee('Rincian Pembawa');
        $this->actingAs($this->reg)->get('/laporan')->assertOk()
            ->assertSee('Daftar Pendaftar')->assertSee('Daftar Berkas Kurang')->assertSee('Rekap Data Lengkap Siswa')->assertDontSee('Rekap Siswa Yatim')->assertDontSee('Rekap Siswa Berprestasi')->assertDontSee('Rekap Reward Pembawa')->assertDontSee('Rekap Pembayaran Harian');
        $this->actingAs($this->clerk)->get('/laporan')->assertOk()
            ->assertSee('Rekap Pembayaran Harian')->assertSee('Daftar Tunggakan')->assertDontSee('Daftar Pendaftar')->assertDontSee('Rekap Data Lengkap Siswa')->assertDontSee('Rekap Siswa Yatim')->assertDontSee('Rekap Siswa Berprestasi')->assertDontSee('Rekap Reward Pembawa');
        $this->actingAs($this->staff)->get('/laporan')->assertOk()
            ->assertSee('Rekap Kebutuhan Ukuran Seragam')->assertDontSee('Daftar Tunggakan')->assertDontSee('Rekap Pembayaran Harian')->assertDontSee('Rekap Data Lengkap Siswa')->assertDontSee('Rekap Siswa Yatim')->assertDontSee('Rekap Siswa Berprestasi');
    }

    public function test_report_access_rules(): void
    {
        $allowed = [
            'pendaftar' => [$this->admin, $this->reg], 'berkas-kurang' => [$this->admin, $this->reg],
            'rekap-cek-berkas' => [$this->admin, $this->reg],
            'daftar-ulang' => [$this->admin, $this->clerk], 'pembayaran-harian' => [$this->admin, $this->clerk],
            'tunggakan' => [$this->admin, $this->clerk], 'keuangan' => [$this->admin, $this->clerk], 'diskon' => [$this->admin, $this->clerk],
            'reward' => [$this->admin],
            'siswa-yatim' => [$this->admin],
            'siswa-berprestasi' => [$this->admin],
            'seragam-belum-ambil' => [$this->admin, $this->staff], 'seragam-penyerahan' => [$this->admin, $this->staff],
            'seragam-ukuran' => [$this->admin, $this->staff], 'seragam-mundur' => [$this->admin, $this->staff],
        ];

        foreach ($allowed as $key => $users) {
            foreach ([$this->admin, $this->reg, $this->clerk, $this->staff] as $user) {
                $response = $this->actingAs($user)->get("/laporan/{$key}");
                in_array($user, $users, true) ? $response->assertOk() : $response->assertForbidden();
            }
        }

        $this->actingAs($this->admin)->get('/laporan/tidak-ada')->assertNotFound();
        $this->actingAs($this->admin)->get('/laporan/reward-rincian')->assertNotFound(); // tanpa pembawa
        $this->actingAs($this->reg)->get('/laporan/reward-rincian?referrer_name=Budi')->assertForbidden();
        $this->actingAs($this->reg)->get('/laporan/siswa-yatim?format=xlsx')->assertForbidden();
        $this->actingAs($this->reg)->get('/laporan/siswa-berprestasi?format=xlsx')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/laporan')->assertRedirect('/login');
        $this->get('/laporan/pendaftar')->assertRedirect('/login');
    }

    public function test_no_active_year_blocks_reports_and_only_admin_can_pick_another_year(): void
    {
        $old = AcademicYear::create(['name' => '2025/2026', 'registration_fee' => 0, 'uniform_threshold_percent' => 0, 'is_active' => false]);
        $this->applicant(1, ['name' => 'Murid Baru']);
        $this->applicant(2, ['name' => 'Murid Lama', 'academic_year_id' => $old->id]);

        $this->actingAs($this->reg)->get("/laporan/pendaftar?year={$old->id}")->assertSee('Murid Baru')->assertDontSee('Murid Lama');
        $this->actingAs($this->admin)->get("/laporan/pendaftar?year={$old->id}")->assertSee('Murid Lama')->assertDontSee('Murid Baru');

        $this->year->update(['is_active' => false]);
        $this->actingAs($this->reg)->get('/laporan/pendaftar')->assertForbidden();
    }

    // ---------- pendaftaran ----------

    public function test_applicant_list_report_and_filters(): void
    {
        $a = $this->applicant(1, ['name' => 'Murid A']);
        $b = $this->applicant(2, [
            'name' => 'Murid B',
            'school_id' => $this->school2->id,
            'student_phone' => '081234567899',
            'referrer_name' => $this->refX,
            'referrer_class_name' => 'XI A',
        ]);
        \Illuminate\Support\Facades\DB::table('applicants')->where('id', $a->id)->update(['created_at' => '2026-12-04 09:00:00']);

        $all = $this->build('pendaftar');
        $this->assertSame(['Murid A', 'Murid B'], $this->names($all, 2));
        $this->assertSame('081234567899', $this->rows($all)[1][9]);
        $this->assertSame('081234567890', $this->rows($all)[1][10]);
        $this->assertSame('Budi Santoso', $this->rows($all)[1][13]);
        $this->assertSame('XI A', $this->rows($all)[1][14]);
        $this->assertSame('25/12/2010', $this->rows($all)[0][5]);

        $this->assertSame(['Murid B'], $this->names($this->build('pendaftar', ['school_id' => $this->school2->id]), 2));
        $this->assertSame(['Murid A', 'Murid B'], $this->names($this->build('pendaftar', ['school_id' => [$this->school->id, $this->school2->id]]), 2));
        $this->assertSame(['Murid B'], $this->names($this->build('pendaftar', ['from' => '2026-12-05']), 2));
        $this->assertSame(['Murid A'], $this->names($this->build('pendaftar', ['to' => '2026-12-04']), 2));

        $this->actingAs($this->reg)->get('/laporan/pendaftar?from=05/12/2026')->assertSee('Murid B')->assertDontSee('Murid A');
        $this->actingAs($this->reg)->get('/laporan/pendaftar?school_id[]='.$this->school->id.'&school_id[]='.$this->school2->id)
            ->assertOk()->assertSee('Murid A')->assertSee('Murid B')
            ->assertSee('type="checkbox" name="school_id[]"', false);
    }

    public function test_missing_documents_report(): void
    {
        $this->applicant(1, ['name' => 'Murid Kurang']);
        $this->applicant(2, ['name' => 'Murid Lengkap', 'documents_complete' => true]);

        $report = $this->build('berkas-kurang');

        $this->assertSame(['Murid Kurang'], $this->names($report, 2));
        $this->assertStringContainsString('Akta kelahiran', $this->rows($report)[0][5]);
    }

    public function test_document_check_report_includes_all_applicants_and_document_statuses(): void
    {
        $complete = $this->applicant(1, [
            'name' => 'Berkas Lengkap',
            'documents_complete' => true,
            'student_phone' => '081234567899',
        ]);
        $incomplete = $this->applicant(2, ['name' => 'Berkas Belum Lengkap', 'school_id' => $this->school2->id]);
        $occupation = Occupation::create(['name' => 'Wiraswasta', 'sort_order' => 1, 'is_active' => true]);
        ApplicantFamilyDetail::create([
            'applicant_id' => $complete->id,
            'academic_year_id' => $this->year->id,
            'kk_number' => '3319010101010001',
            'student_nik' => '3319014501100001',
            'father_nik' => '3319010101800001',
            'father_birth_year' => 1980,
            'father_occupation_id' => $occupation->id,
            'mother_nik' => '3319014101850001',
            'mother_birth_year' => 1985,
            'mother_occupation_id' => $occupation->id,
            'guardian_name' => 'Kakek Siswa',
            'guardian_nik' => '3319010101500009',
            'guardian_birth_year' => 1950,
            'guardian_occupation_id' => $occupation->id,
        ]);
        foreach (DocumentType::cases() as $type) {
            if ($type->group() === 'wajib' || in_array($type, [DocumentType::KtpAyah, DocumentType::KtpIbu], true)) {
                ApplicantDocument::create([
                    'applicant_id' => $complete->id,
                    'type' => $type,
                    'status' => DocumentStatus::Diterima,
                ]);
            }
        }
        ApplicantDocument::create(['applicant_id' => $complete->id, 'type' => DocumentType::Sktm, 'status' => DocumentStatus::TidakAda]);
        ApplicantDocument::create([
            'applicant_id' => $complete->id, 'type' => DocumentType::Kks, 'status' => DocumentStatus::Diterima,
            'card_number' => 'KKS-123',
        ]);
        ApplicantDocument::create([
            'applicant_id' => $complete->id, 'type' => DocumentType::Pkh, 'status' => DocumentStatus::Diterima,
            'card_number' => 'KIP-456',
        ]);
        ApplicantDocument::create([
            'applicant_id' => $complete->id, 'type' => DocumentType::SuratPeringkat, 'status' => DocumentStatus::Diterima,
            'ranking_type' => 'paralel', 'rank_position' => 2,
        ]);
        ApplicantDocument::create(['applicant_id' => $complete->id, 'type' => DocumentType::Sertifikat, 'status' => DocumentStatus::TidakAda]);

        $report = $this->build('rekap-cek-berkas');
        $rows = $this->rows($report);
        $columns = array_column($report->sections[0]['columns'], 'label');
        $column = fn (string $label) => array_search($label, $columns, true);

        $this->assertSame(['Berkas Lengkap', 'Berkas Belum Lengkap'], $this->names($report, 2));
        $this->assertSame('Lengkap', $rows[0][$column('Kelengkapan berkas')]);
        $this->assertSame('Belum lengkap', $rows[1][$column('Kelengkapan berkas')]);
        $this->assertSame('Diterima', $rows[0][$column('Status: Kartu KKS/PKH')]);
        $this->assertSame('Tidak ada', $rows[0][$column('Status: Sertifikat kejuaraan')]);
        $this->assertSame('Belum diterima', $rows[1][$column('Status: KTP ayah')]);
        $this->assertSame('3319014501100001', $rows[0][$column('NIK murid')]);
        $this->assertSame('081234567899', $rows[0][$column('No. HP siswa')]);
        $this->assertSame('081234567890', $rows[0][$column('No. HP orang tua/wali')]);
        $this->assertSame('Ayah 1', $rows[0][$column('Nama ayah')]);
        $this->assertSame('3319010101800001', $rows[0][$column('NIK ayah')]);
        $this->assertSame('Wiraswasta', $rows[0][$column('Pekerjaan ayah')]);
        $this->assertSame(1980, $rows[0][$column('Tahun lahir ayah')]);
        $this->assertSame('Ibu 1', $rows[0][$column('Nama ibu')]);
        $this->assertSame('3319014101850001', $rows[0][$column('NIK ibu')]);
        $this->assertSame('Wiraswasta', $rows[0][$column('Pekerjaan ibu')]);
        $this->assertSame(1985, $rows[0][$column('Tahun lahir ibu')]);
        $this->assertSame('Kakek Siswa', $rows[0][$column('Nama wali')]);
        $this->assertSame('3319010101500009', $rows[0][$column('NIK wali')]);
        $this->assertSame('Wiraswasta', $rows[0][$column('Pekerjaan wali')]);
        $this->assertSame(1950, $rows[0][$column('Tahun lahir wali')]);
        $this->assertSame('3319010101010001', $rows[0][$column('Nomor KK')]);
        $this->assertSame('KIP-456', $rows[0][$column('Nomor KIP')]);
        $this->assertSame('KKS-123', $rows[0][$column('Nomor KKS/PKH')]);
        $this->assertSame('Paralel', $rows[0][$column('Jenis peringkat')]);
        $this->assertSame(2, $rows[0][$column('Peringkat')]);
        $this->assertSame(count(DocumentType::cases()) + 25, count($columns));

        $this->actingAs($this->reg)->get('/laporan/rekap-cek-berkas')->assertOk()
            ->assertSee('Berkas Lengkap')->assertSee('Berkas Belum Lengkap')
            ->assertSee('Rekap Data Lengkap Siswa')->assertSee('KKS-123')->assertSee('Belum diterima')
            ->assertSee('3319014501100001')->assertSee('1950');
        $this->actingAs($this->clerk)->get('/laporan/rekap-cek-berkas')->assertForbidden();
    }

    public function test_orphan_report_includes_applicants_missing_either_parent_ktp_once(): void
    {
        $fatherAbsent = $this->applicant(1, ['name' => 'Ayah Tidak Ada']);
        $motherAbsent = $this->applicant(2, ['name' => 'Ibu Tidak Ada', 'school_id' => $this->school2->id]);
        $bothAbsent = $this->applicant(3, ['name' => 'Keduanya Tidak Ada']);
        $familyFlagOnly = $this->applicant(4, ['name' => 'Hanya Data KK']);
        $complete = $this->applicant(5, ['name' => 'KTP Lengkap']);
        $archived = AcademicYear::create([
            'name' => '2025/2026', 'registration_fee' => 0, 'uniform_threshold_percent' => 0, 'is_active' => false,
        ]);
        $this->applicant(6, ['name' => 'Arsip', 'academic_year_id' => $archived->id]);

        ApplicantDocument::create(['applicant_id' => $fatherAbsent->id, 'type' => 'ktp_ayah', 'status' => DocumentStatus::TidakAda]);
        ApplicantDocument::create(['applicant_id' => $motherAbsent->id, 'type' => 'ktp_ibu', 'status' => DocumentStatus::TidakAda]);
        ApplicantDocument::create(['applicant_id' => $bothAbsent->id, 'type' => 'ktp_ayah', 'status' => DocumentStatus::TidakAda]);
        ApplicantDocument::create(['applicant_id' => $bothAbsent->id, 'type' => 'ktp_ibu', 'status' => DocumentStatus::TidakAda]);
        ApplicantDocument::create(['applicant_id' => $familyFlagOnly->id, 'type' => 'ktp_ayah', 'status' => DocumentStatus::Belum]);
        ApplicantDocument::create(['applicant_id' => $complete->id, 'type' => 'ktp_ayah', 'status' => DocumentStatus::Diterima]);
        ApplicantDocument::create(['applicant_id' => $complete->id, 'type' => 'ktp_ibu', 'status' => DocumentStatus::Diterima]);

        $report = $this->build('siswa-yatim');

        $this->assertSame(['Ayah Tidak Ada', 'Ibu Tidak Ada', 'Keduanya Tidak Ada'], $this->names($report, 2));
        $this->assertSame('Tidak ada/almarhum', $this->rows($report)[0][4]);
        $this->assertSame('Belum diterima', $this->rows($report)[0][5]);
        $this->assertSame('Ayah dan Ibu', $this->rows($report)[2][6]);
        $this->assertSame('SMP 2', $this->rows($report)[1][3]);

        $this->actingAs($this->admin)->get('/laporan/siswa-yatim')->assertOk()
            ->assertSee('Rekap Siswa Yatim')
            ->assertSee('Keduanya Tidak Ada')
            ->assertDontSee('Hanya Data KK')
            ->assertDontSee('Arsip');
    }

    public function test_achievement_report_includes_accepted_ranking_or_certificate_once(): void
    {
        $ranking = $this->applicant(1, ['name' => 'Juara Kelas']);
        $certificate = $this->applicant(2, ['name' => 'Juara Kejuaraan', 'school_id' => $this->school2->id]);
        $both = $this->applicant(3, ['name' => 'Juara Keduanya']);
        $notAccepted = $this->applicant(4, ['name' => 'Belum Diterima']);
        $noAchievement = $this->applicant(5, ['name' => 'Tanpa Prestasi']);

        ApplicantDocument::create([
            'applicant_id' => $ranking->id, 'type' => 'surat_peringkat', 'status' => DocumentStatus::Diterima,
            'ranking_type' => 'kelas', 'rank_position' => 1,
        ]);
        ApplicantDocument::create(['applicant_id' => $certificate->id, 'type' => 'sertifikat', 'status' => DocumentStatus::Diterima]);
        ApplicantDocument::create([
            'applicant_id' => $both->id, 'type' => 'surat_peringkat', 'status' => DocumentStatus::Diterima,
            'ranking_type' => 'paralel', 'rank_position' => 2,
        ]);
        ApplicantDocument::create(['applicant_id' => $both->id, 'type' => 'sertifikat', 'status' => DocumentStatus::Diterima]);
        ApplicantDocument::create(['applicant_id' => $notAccepted->id, 'type' => 'sertifikat', 'status' => DocumentStatus::Belum]);
        ApplicantDocument::create(['applicant_id' => $noAchievement->id, 'type' => 'surat_peringkat', 'status' => DocumentStatus::TidakAda]);

        $report = $this->build('siswa-berprestasi');
        $rows = $this->rows($report);

        $this->assertSame(['Juara Kelas', 'Juara Kejuaraan', 'Juara Keduanya'], $this->names($report, 2));
        $this->assertSame('Kelas - peringkat 1', $rows[0][5]);
        $this->assertSame('Diterima', $rows[1][6]);
        $this->assertSame('Paralel - peringkat 2', $rows[2][5]);
        $this->assertSame(3, count($rows));

        $this->actingAs($this->admin)->get('/laporan/siswa-berprestasi')->assertOk()
            ->assertSee('Rekap Siswa Berprestasi')
            ->assertSee('Juara Kejuaraan')
            ->assertDontSee('Belum Diterima')
            ->assertDontSee('Tanpa Prestasi');
    }

    // ---------- daftar ulang dan keuangan ----------

    public function test_enrollment_list_filters_by_status(): void
    {
        $this->enrollmentScenario();

        $names = fn (?string $status) => $this->names($this->build('daftar-ulang', ['status' => $status]), 2);

        $this->assertSame(['Murid A', 'Murid B', 'Murid C', 'Murid D', 'Murid E', 'Murid F', 'Murid G'], $names(null));
        $this->assertSame(['Murid A', 'Murid B', 'Murid F'], $names('sudah'));
        $this->assertSame(['Murid A', 'Murid B', 'Murid D', 'Murid E', 'Murid F'], $this->names(
            $this->build('daftar-ulang', ['status' => ['sudah', 'mundur']]), 2
        ));
        $this->assertSame(['Murid C', 'Murid G'], $names('belum'));
        $this->assertSame(['Murid A', 'Murid F'], $names('menyicil'));
        $this->assertSame(['Murid B'], $names('lunas'));
        $this->assertSame(['Murid D', 'Murid E'], $names('mundur'));
        $this->actingAs($this->clerk)->get('/laporan/daftar-ulang?status[]=sudah&status[]=mundur')
            ->assertOk()->assertSee('Murid A')->assertSee('Murid D')->assertDontSee('Murid C')
            ->assertSee('type="checkbox" name="status[]"', false);
    }

    public function test_daily_payments_report_totals_per_day_and_per_receiver(): void
    {
        $second = User::factory()->role(Role::DaftarUlang)->create(['name' => 'Zaenal Kasir']);
        $this->clerk->update(['name' => 'Ani Kasir']);

        $a = $this->applicant(1, ['name' => 'Murid A']);
        $b = $this->applicant(2, ['name' => 'Murid B']);
        $c = $this->applicant(3, ['name' => 'Murid C']);

        Carbon::setTestNow('2026-12-04 09:00:00');
        $this->pay($c, 300000);
        Carbon::setTestNow('2026-12-05 10:00:00');

        $this->pay($a, 500000);
        $this->pay($b, 1000000, $second);
        $cancelled = $this->pay($a, 100000);
        app(EnrollmentService::class)->cancelPayment($cancelled, 'salah input', $this->admin);

        $today = $this->build('pembayaran-harian');
        $this->assertCount(3, $this->rows($today, 0));                      // termasuk yang dibatalkan
        $this->assertSame('Dibatalkan', $this->rows($today, 0)[2][6]);
        $this->assertSame([['05/12/2026', 2, 1500000]], $this->rows($today, 1));
        $this->assertSame(['Ani Kasir' => 500000, 'Zaenal Kasir' => 1000000], array_column($this->rows($today, 2), 2, 0));
        $this->assertSame(['Total', 2, 1500000], $this->footer($today, 1));

        $range = $this->build('pembayaran-harian', ['from' => '2026-12-04', 'to' => '2026-12-05']);
        $this->assertSame([['04/12/2026', 1, 300000], ['05/12/2026', 2, 1500000]], $this->rows($range, 1));
        $this->assertSame(['Total', 3, 1800000], $this->footer($range, 1));

        $this->actingAs($this->clerk)->get('/laporan/pembayaran-harian?from=04/12/2026&to=05/12/2026')
            ->assertOk()->assertSee('Total per hari')->assertSee('Rp 1.800.000')->assertSee('Rp 300.000');
    }

    public function test_arrears_report_lists_only_installment_students(): void
    {
        $this->enrollmentScenario();

        $report = $this->build('tunggakan');

        $this->assertSame(['Murid A', 'Murid F'], $this->names($report, 2));   // A menyicil, F menyicil (100.000)
        $this->assertSame(['Total', '', '', '', 3000000, 600000, 2400000, ''], $this->footer($report));
    }

    public function test_finance_report_summary_and_refund_note_visibility(): void
    {
        $s = $this->enrollmentScenario();
        app(EnrollmentService::class)->setRefundNote($s['d'], 'Dikembalikan Rp 500.000 tanggal 6 Des');

        $summary = array_column($this->rows($this->build('keuangan'), 0), 1, 0);

        $this->assertSame(7, $summary['Jumlah pendaftar']);
        $this->assertSame(2, $summary['Mengundurkan diri']);
        $this->assertSame(4500000, $summary['Tagihan kotor (murid yang sudah membayar, tidak mundur)']);
        $this->assertSame(4500000, $summary['Tagihan bersih']);
        $this->assertSame(2100000, $summary['Total diterima dari murid tidak mundur']);   // 500k + 1,5jt + 100k
        $this->assertSame(2400000, $summary['Sisa tagihan']);
        $this->assertSame(500000, $summary['Uang dari murid yang mundur']);
        $this->assertSame(2600000, $summary['Total uang masuk (semua pembayaran sah)']);

        $this->actingAs($this->admin)->get('/laporan/keuangan')->assertOk()->assertSee('Catatan pengembalian')->assertSee('Dikembalikan Rp 500.000 tanggal 6 Des');
        $this->actingAs($this->clerk)->get('/laporan/keuangan')->assertOk()->assertSee('Murid D')->assertDontSee('Catatan pengembalian')->assertDontSee('Dikembalikan Rp 500.000');
    }

    public function test_discount_report_groups_periods_and_manual_discounts(): void
    {
        DiscountPeriod::create(['academic_year_id' => $this->year->id, 'name' => 'Natal', 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-31', 'percent' => 10]);
        $a = $this->applicant(1, ['name' => 'Murid A']);
        $b = $this->applicant(2, ['name' => 'Murid B']);
        $c = $this->applicant(3, ['name' => 'Murid C']);

        $this->pay($a, 100000);   // diskon periode 10%: bersih 1.350.000
        $this->pay($b, 100000);   // diskon periode 10%
        app(EnrollmentService::class)->setManualDiscount($c, 20, 'Anak yatim', $this->admin);   // bersih 1.200.000

        $report = $this->build('diskon');

        $this->assertSame([['Khusus (admin)', 'Diskon khusus', '20%', 1, 300000], ['Periode', 'Natal', '10%', 2, 300000]], $this->rows($report, 0));
        $this->assertSame(['Total', '', '', 3, 600000], $this->footer($report, 0));
        $this->assertSame(['Murid A', 'Murid B', 'Murid C'], $this->names($report, 2, 1));
        $this->assertSame('Anak yatim', $this->rows($report, 1)[2][10]);
    }

    // ---------- reward ----------

    public function test_reward_report_counts_paid_unpaid_and_withdrawn_per_referrer(): void
    {
        $this->enrollmentScenario();

        $report = $this->build('reward');
        $rows = $this->rows($report);

        // [No, Nama pembawa, Kelas, Total, Dihitung reward, Belum bayar, Mundur]
        $this->assertSame([1, 'Budi Santoso', 'XI A', 5, 2, 1, 2], $rows[0]);
        $this->assertSame([2, 'Siti Aminah', 'XI B', 1, 1, 0, 0], $rows[1]);
        $this->assertSame(['Total', '', '', 6, 3, 1, 2], $this->footer($report));
        $this->assertStringContainsString('1 pendaftar tidak memiliki pembawa', implode(' ', $report->notes));
    }

    public function test_reward_follows_valid_payments_when_a_payment_is_cancelled(): void
    {
        $a = $this->applicant(1, ['referrer_name' => $this->refX, 'referrer_class_name' => 'XI A']);
        $payment = $this->pay($a, 100000);
        $this->assertSame([1, 'Budi Santoso', 'XI A', 1, 1, 0, 0], $this->rows($this->build('reward'))[0]);

        app(EnrollmentService::class)->cancelPayment($payment, 'salah input', $this->admin);
        $this->assertSame([1, 'Budi Santoso', 'XI A', 1, 0, 1, 0], $this->rows($this->build('reward'))[0]);
    }

    public function test_reward_groups_applicant_referrer_values_by_name_and_class(): void
    {
        $this->applicant(1, ['referrer_name' => 'Budi Santoso', 'referrer_class_name' => 'XI A']);
        $this->applicant(2, ['referrer_name' => 'BUDI SANTOSO', 'referrer_class_name' => 'xi a']);
        $this->applicant(3, ['referrer_name' => 'Budi Santoso', 'referrer_class_name' => 'XI B']);

        $rows = $this->rows($this->build('reward'));

        $this->assertCount(2, $rows);
        $this->assertSame('budi santoso', mb_strtolower($rows[0][1]));
        $this->assertSame('xi a', mb_strtolower($rows[0][2]));
        $this->assertSame(2, $rows[0][3]);
        $this->assertSame('budi santoso', mb_strtolower($rows[1][1]));
        $this->assertSame('xi b', mb_strtolower($rows[1][2]));
        $this->assertSame(1, $rows[1][3]);

        $detail = $this->build('reward-rincian', [
            'referrer_name' => 'Budi Santoso',
            'referrer_class_name' => 'XI A',
            'kategori' => 'semua',
        ]);
        $this->assertSame(['Murid 1', 'Murid 2'], $this->names($detail, 2));
    }

    public function test_reward_class_filter_limits_the_report_and_keeps_custom_classes_available(): void
    {
        $this->applicant(1, ['referrer_name' => 'Budi Santoso', 'referrer_class_name' => 'XI.1']);
        $this->applicant(2, ['referrer_name' => 'Siti Aminah', 'referrer_class_name' => 'Kelas Khusus']);

        $this->actingAs($this->admin)->get('/laporan/reward')
            ->assertOk()->assertSee('Kelas pembawa')->assertSee('Kelas Khusus')->assertSee('XII.6');

        $report = $this->build('reward', ['referrer_class_filter' => 'Kelas Khusus']);
        $this->assertCount(1, $this->rows($report));
        $this->assertSame('Siti Aminah', $this->rows($report)[0][1]);
        $this->assertSame('Kelas Khusus', $this->rows($report)[0][2]);
    }

    public function test_reward_detail_pages_and_links(): void
    {
        $this->enrollmentScenario();

        $this->actingAs($this->admin)->get('/laporan/reward')->assertOk()
            ->assertSee('kategori=reward', false)->assertSee('referrer_name=Budi', false)->assertSee('referrer_class_name=XI', false);

        $detail = fn (string $kategori) => $this->names($this->build('reward-rincian', [
            'referrer_name' => $this->refX, 'referrer_class_name' => 'XI A', 'kategori' => $kategori,
        ]), 2);
        $this->assertSame(['Murid A', 'Murid B'], $detail('reward'));
        $this->assertSame(['Murid C'], $detail('belum'));
        $this->assertSame(['Murid D', 'Murid E'], $detail('mundur'));
        $this->assertCount(5, $detail('semua'));

        $this->actingAs($this->admin)->get('/laporan/reward-rincian?referrer_name=Budi%20Santoso&referrer_class_name=XI%20A&kategori=mundur')
            ->assertOk()->assertSee('Rincian Pembawa: Budi Santoso (XI A)')->assertSee('Murid D')->assertDontSee('Murid A');
    }

    // ---------- seragam ----------

    /** S1 berhak belum ambil (ukuran M), S2 sebagian, S3 lengkap, S4 belum berhak, S5 berhak lalu mundur setelah ambil. */
    private function uniformScenario(): array
    {
        $shirt = UniformType::create(['academic_year_id' => $this->year->id, 'name' => 'Baju Putih', 'sort_order' => 10, 'is_active' => true]);
        $pe = UniformType::create(['academic_year_id' => $this->year->id, 'name' => 'Olahraga', 'needs_size' => true, 'sort_order' => 20, 'is_active' => true]);
        $m = UniformSize::create(['name' => 'M', 'sort_order' => 20, 'is_active' => true]);
        $l = UniformSize::create(['name' => 'L', 'sort_order' => 30, 'is_active' => true]);
        $svc = app(UniformService::class);

        $make = function (int $seq, string $name, int $amount) {
            $a = $this->applicant($seq, ['name' => $name]);
            $this->pay($a, $amount);

            return $a;
        };

        $s1 = $make(1, 'Murid S1', 900000);
        $s2 = $make(2, 'Murid S2', 900000);
        $s3 = $make(3, 'Murid S3', 900000);
        $s4 = $make(4, 'Murid S4', 100000);
        $s5 = $make(5, 'Murid S5', 900000);

        $svc->setSize($s1, $pe, $m, $this->staff);
        $svc->handOver($s2, $shirt, $this->staff);
        $svc->setSize($s3, $pe, $l, $this->staff);
        $svc->handOver($s3, $shirt, $this->staff);
        $svc->handOver($s3, $pe, $this->staff);
        $svc->handOver($s5, $shirt, $this->staff);
        app(EnrollmentService::class)->withdraw($s5, '2026-12-05', 'Pindah', $this->admin);

        return compact('shirt', 'pe', 's1', 's2', 's3', 's4', 's5');
    }

    public function test_uniform_not_picked_report(): void
    {
        $this->uniformScenario();

        $none = $this->build('seragam-belum-ambil');
        $this->assertSame(['Murid S1'], $this->names($none, 2));
        $this->assertSame('Olahraga: M', $this->rows($none)[0][6]);
        $this->assertSame(900000, $this->rows($none)[0][4]);

        $incomplete = $this->build('seragam-belum-ambil', ['scope' => 'belum_lengkap']);
        $this->assertSame(['Murid S1', 'Murid S2'], $this->names($incomplete, 2));
        $this->assertSame('1/2: Baju Putih', $this->rows($incomplete)[1][7]);
        $bothScopes = $this->build('seragam-belum-ambil', ['scope' => ['belum', 'belum_lengkap']]);
        $this->assertSame(['Murid S1', 'Murid S2'], $this->names($bothScopes, 2));
        $this->actingAs($this->staff)->get('/laporan/seragam-belum-ambil?scope[]=belum&scope[]=belum_lengkap')
            ->assertOk()->assertSee('type="checkbox" name="scope[]"', false);
    }

    public function test_uniform_handover_report_counts_per_type_and_day(): void
    {
        $this->uniformScenario();

        $report = $this->build('seragam-penyerahan');
        $this->assertSame([['Baju Putih', 3], ['Olahraga', 1]], $this->rows($report, 0));
        $this->assertSame(['Total', 4], $this->footer($report, 0));
        $this->assertSame([['05/12/2026', 'Baju Putih', 3], ['05/12/2026', 'Olahraga', 1]], $this->rows($report, 1));

        $this->assertSame([], $this->rows($this->build('seragam-penyerahan', ['from' => '2026-12-06']), 0));
        $this->assertSame(4, array_sum(array_column($this->rows($this->build('seragam-penyerahan', ['from' => '2026-12-05', 'to' => '2026-12-05']), 0), 1)));
    }

    public function test_uniform_size_need_report(): void
    {
        $this->uniformScenario();

        $all = $this->build('seragam-ukuran');
        $this->assertSame('Olahraga', $all->sections[0]['title']);
        $this->assertSame([['M', 1], ['L', 1], ['Ukuran belum dicatat', 1]], $this->rows($all));   // S1 M, S3 L, S2 belum
        $this->assertSame(['Total murid berhak', 3], $this->footer($all));                          // S5 mundur dan S4 tidak dihitung

        $notPicked = $this->build('seragam-ukuran', ['scope' => 'belum_diambil']);
        $this->assertSame([['M', 1], ['Ukuran belum dicatat', 1]], $this->rows($notPicked));       // S3 sudah ambil olahraga
        $this->assertSame(['Total murid berhak', 2], $this->footer($notPicked));
        $bothScopes = $this->build('seragam-ukuran', ['scope' => ['semua', 'belum_diambil']]);
        $this->assertSame([['M', 1], ['L', 1], ['Ukuran belum dicatat', 1]], $this->rows($bothScopes));
        $this->actingAs($this->staff)->get('/laporan/seragam-ukuran')->assertOk()
            ->assertSee('type="checkbox" name="scope[]"', false);
    }

    public function test_withdrawn_with_uniform_report_and_privacy_for_uniform_staff(): void
    {
        $this->uniformScenario();

        $report = $this->build('seragam-mundur');
        $this->assertSame(['Murid S5'], $this->names($report, 2));
        $this->assertSame('Baju Putih', $this->rows($report)[0][5]);

        foreach (['seragam-belum-ambil', 'seragam-penyerahan', 'seragam-ukuran', 'seragam-mundur'] as $key) {
            $this->actingAs($this->staff)->get("/laporan/{$key}")->assertOk()
                ->assertDontSee('081234567890')->assertDontSee('BAKALAN')->assertDontSee('Ayah ')->assertDontSee('Ibu ');
        }
    }

    // ---------- ekspor Excel dan cetak ----------

    public function test_xlsx_download_is_a_valid_workbook(): void
    {
        $this->applicant(1, ['name' => 'Budi & <Santoso>']);

        $response = $this->actingAs($this->reg)->get('/laporan/pendaftar?format=xlsx');

        $response->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="laporan-pendaftar-2026-2027-20261205.xlsx"', $response->headers->get('Content-Disposition'));

        $parts = $this->xlsxParts($response->getContent());
        foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml', 'xl/worksheets/sheet1.xml'] as $name) {
            $this->assertArrayHasKey($name, $parts);
            $this->assertNotFalse(simplexml_load_string($parts[$name]), "{$name} bukan XML yang valid");
        }

        $sheet = $parts['xl/worksheets/sheet1.xml'];
        $this->assertStringContainsString('Daftar Pendaftar', $sheet);
        $this->assertStringContainsString('Asal sekolah', $sheet);
        $this->assertStringContainsString('Budi &amp; &lt;Santoso&gt;', $sheet);
        $this->assertStringContainsString('081234567890', $sheet);
    }

    public function test_xlsx_writer_keeps_numbers_numeric_and_digit_strings_as_text(): void
    {
        $report = (new Report('Uji & Coba', 'TA 2026/2027', ['Catatan a'."\x01".'b']))
            ->section('Bagian', [Report::col('NIK'), Report::col('Dibayar', 'rp'), Report::col('Jumlah', 'int'), Report::col('Ket')], [
                ['3319010101010001', 1500000, Report::num(3), ''],
            ], ['Total', Report::rp(1500000), 3, '']);

        $sheet = $this->xlsxParts(XlsxWriter::toString($report))['xl/worksheets/sheet1.xml'];
        $xml = simplexml_load_string($sheet);

        $this->assertNotFalse($xml);
        $this->assertStringContainsString('Uji &amp; Coba', $sheet);
        $this->assertStringContainsString('Catatan ab', $sheet);                                    // karakter kontrol dibuang
        // baris 1 judul, 2 subjudul, 4 judul bagian, 5 header, 6 data, 7 total
        $this->assertMatchesRegularExpression('#<c r="A6" t="inlineStr" s="0"><is><t[^>]*>3319010101010001</t>#', $sheet);   // NIK tetap teks
        $this->assertStringContainsString('<c r="B6" s="3"><v>1500000</v></c>', $sheet);            // nominal sebagai angka berformat ribuan
        $this->assertStringContainsString('<c r="C6" s="3"><v>3</v></c>', $sheet);
        $this->assertStringContainsString('<c r="B7" s="4"><v>1500000</v></c>', $sheet);            // baris total tebal
        $this->assertStringContainsString('<c r="C7" s="4"><v>3</v></c>', $sheet);
        $this->assertStringContainsString('<c r="A5" t="inlineStr" s="2">', $sheet);                // header
        $this->assertStringNotContainsString('r="D6"', $sheet);                                     // sel kosong dilewati

        $this->assertSame('A', XlsxWriter::columnLetter(0));
        $this->assertSame('Z', XlsxWriter::columnLetter(25));
        $this->assertSame('AA', XlsxWriter::columnLetter(26));
        $this->assertSame('ZZ', XlsxWriter::columnLetter(701));
        $this->assertSame('AAA', XlsxWriter::columnLetter(702));
    }

    public function test_print_view_has_school_header_and_no_screen_controls(): void
    {
        config(['sekolah.name' => 'SMA CONTOH 1']);
        $this->applicant(1, ['name' => 'Murid A']);

        $this->actingAs($this->reg)->get('/laporan/pendaftar?format=print')
            ->assertOk()->assertSee('SMA CONTOH 1')->assertSee('Daftar Pendaftar')->assertSee('Murid A')
            ->assertSee('Dicetak oleh '.$this->reg->name.' pada 05/12/2026 10:00')
            ->assertDontSee('Unduh Excel');
    }

    public function test_screen_view_offers_excel_and_print(): void
    {
        $this->actingAs($this->admin)->get('/laporan/pendaftar')
            ->assertOk()->assertSee('Unduh Excel')->assertSee('Cetak / Simpan PDF')->assertSee('format=xlsx', false)->assertSee('format=print', false)
            ->assertSee('type="checkbox" name="school_id[]"', false);
        $this->actingAs($this->clerk)->get('/laporan/daftar-ulang')
            ->assertOk()->assertSee('type="checkbox" name="status[]"', false)
            ->assertSee('style="width: 180px;"', false)
            ->assertSee('style="height: 38px; display: flex;"', false)
            ->assertSee('<svg width="16" height="16"', false);
    }
}
