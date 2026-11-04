<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus as S;
use App\Enums\DocumentType;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\ApplicantFamilyDetail;
use App\Models\Occupation;
use App\Models\School;
use App\Models\User;
use App\Services\DocumentChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year;
    private School $school;
    private User $user;
    private Occupation $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = AcademicYear::create(['name' => '2026/2027', 'registration_fee' => 0, 'uniform_threshold_percent' => 0, 'is_active' => true]);
        $this->school = School::create(['name' => 'SMP 1', 'regency_code' => '33.19', 'regency_name' => 'KAB. KUDUS']);
        $this->job = Occupation::create(['name' => 'Petani/Pekebun']);
        $this->user = User::factory()->create(); // panitia pendaftaran
    }

    private function makeApplicant(int $seq = 1, array $override = []): Applicant
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
            'created_by' => $this->user->id,
        ], $override));
    }

    private function docs(array $override = []): array
    {
        $docs = [];
        foreach (DocumentType::cases() as $type) {
            $docs[$type->value] = ['status' => 'belum'];
        }
        foreach ($override as $type => $value) {
            $docs[$type] = is_array($value) ? $value : ['status' => $value];
        }

        return $docs;
    }

    private function family(array $override = []): array
    {
        return array_merge([
            'number' => '3319010101010001', 'student_nik' => '3319014501100001',
            'father_name' => 'Ayah 1', 'mother_name' => 'Ibu 1',
            'father_nik' => '3319010101800001', 'father_birth_year' => '1980', 'father_occupation_id' => $this->job->id,
            'mother_nik' => '3319014101850001', 'mother_birth_year' => '1985', 'mother_occupation_id' => $this->job->id,
        ], $override);
    }

    private function submit(Applicant $a, array $docs, array $extra = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->user)->put("/berkas/{$a->id}", ['docs' => $docs] + $extra);
    }

    private function map(array $override = []): array
    {
        $map = [];
        foreach (DocumentType::cases() as $t) {
            $map[$t->value] = S::Belum;
        }

        return array_merge($map, $override);
    }

    // ---------- aturan kelengkapan ----------

    public function test_completeness_rules(): void
    {
        $c = new DocumentChecklist();
        $base = [
            'akta_kelahiran' => S::Diterima, 'kk' => S::Diterima, 'skl' => S::Diterima, 'foto' => S::Diterima,
            'ktp_ayah' => S::Diterima, 'ktp_ibu' => S::Diterima,
            'sktm' => S::TidakAda, 'kks' => S::TidakAda, 'pkh' => S::TidakAda, 'sertifikat' => S::TidakAda,
        ];

        $this->assertTrue($c->isComplete($this->map($base)));

        // wali menggantikan KTP ayah/ibu
        $this->assertTrue($c->isComplete($this->map(['ktp_ayah' => S::TidakAda, 'ktp_ibu' => S::Diterima, 'ktp_wali' => S::Diterima] + $base)));
        $this->assertTrue($c->isComplete($this->map(['ktp_ayah' => S::Belum, 'ktp_ibu' => S::Belum, 'ktp_wali' => S::Diterima] + $base)));

        // satu orang tua tidak ada/almarhum (KTP "Tidak ada"), tanpa wali: LENGKAP
        $this->assertTrue($c->isComplete($this->map(['ktp_ibu' => S::TidakAda] + $base)));
        $this->assertTrue($c->isComplete($this->map(['ktp_ayah' => S::TidakAda] + $base)));

        // satu orang tua diterima, yang lain masih Belum: belum lengkap
        $this->assertSame(['KTP orang tua/wali'], $c->missing($this->map(['ktp_ibu' => S::Belum] + $base)));

        // kedua orang tua tidak ada: wajib KTP wali
        $bothGone = ['ktp_ayah' => S::TidakAda, 'ktp_ibu' => S::TidakAda] + $base;
        $this->assertSame(['KTP wali (kedua orang tua tidak ada)'], $c->missing($this->map($bothGone)));
        $this->assertTrue($c->isComplete($this->map(['ktp_wali' => S::Diterima] + $bothGone)));

        // penanda "tidak ada/almarhum" di data KK juga dihitung, walau status KTP-nya masih Belum
        $fatherFlag = ['father' => true, 'mother' => false];
        $this->assertTrue($c->isComplete($this->map(['ktp_ayah' => S::Belum] + $base), $fatherFlag));
        $this->assertFalse($c->isComplete($this->map(['ktp_ayah' => S::Belum] + $base)));
        $this->assertSame(
            ['KTP wali (kedua orang tua tidak ada)'],
            $c->missing($this->map(['ktp_ayah' => S::Belum, 'ktp_ibu' => S::Belum] + $base), ['father' => true, 'mother' => true]),
        );
        $this->assertTrue($c->isComplete(
            $this->map(['ktp_ayah' => S::Belum, 'ktp_ibu' => S::Belum, 'ktp_wali' => S::Diterima] + $base),
            ['father' => true, 'mother' => true],
        ));

        // kondisional tidak boleh Belum
        $missing = $c->missing($this->map(['kks' => S::Belum] + $base));
        $this->assertSame(['Kartu KKS/PKH (belum diputuskan)'], $missing);

        // wajib kurang
        $this->assertSame(['FC SKL'], $c->missing($this->map(['skl' => S::Belum] + $base)));
    }

    // ---------- menyimpan status ----------

    public function test_saving_statuses_records_handler_log_and_completeness(): void
    {
        $a = $this->makeApplicant();
        $this->assertFalse($a->fresh()->documents_complete);

        $this->submit($a, $this->docs([
            'akta_kelahiran' => 'diterima', 'foto' => 'diterima', 'ktp_wali' => 'diterima',
            'sktm' => 'tidak_ada', 'kks' => 'tidak_ada', 'pkh' => 'tidak_ada',
            'sertifikat' => ['status' => 'diterima', 'note' => 'Juara 2 OSN Kabupaten'],
        ]))->assertSessionHasNoErrors();

        $row = ApplicantDocument::where('applicant_id', $a->id)->where('type', 'akta_kelahiran')->firstOrFail();
        $this->assertSame(S::Diterima, $row->status);
        $this->assertSame($this->user->id, $row->handled_by);
        $this->assertNotNull($row->handled_at);
        $this->assertSame('Juara 2 OSN Kabupaten', ApplicantDocument::where('type', 'sertifikat')->first()->note);
        $this->assertDatabaseHas('activity_logs', ['event' => 'documents.updated', 'subject_id' => $a->id]);
        $this->assertFalse($a->fresh()->documents_complete); // KK dan SKL masih belum

        $this->submit($a, $this->docs([
            'akta_kelahiran' => 'diterima', 'foto' => 'diterima', 'ktp_wali' => 'diterima',
            'sktm' => 'tidak_ada', 'kks' => 'tidak_ada', 'pkh' => 'tidak_ada', 'sertifikat' => 'tidak_ada',
            'skl' => 'diterima', 'kk' => 'diterima',
        ]), ['nisn' => '0012345678', 'family' => $this->family()])->assertSessionHasNoErrors();

        $this->assertTrue($a->fresh()->documents_complete);
    }

    public function test_only_changed_rows_are_logged(): void
    {
        $a = $this->makeApplicant();
        $this->submit($a, $this->docs(['akta_kelahiran' => 'diterima']))->assertSessionHasNoErrors();
        $this->submit($a, $this->docs(['akta_kelahiran' => 'diterima', 'foto' => 'diterima']))->assertSessionHasNoErrors();

        $logs = \App\Models\ActivityLog::where('event', 'documents.updated')->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertSame(['Foto diri' => 'Diterima'], $logs[1]->new_values);
        $this->assertSame(['Foto diri' => 'Belum diterima'], $logs[1]->old_values);
    }

    public function test_no_change_writes_no_log(): void
    {
        $a = $this->makeApplicant();
        $this->submit($a, $this->docs())->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('activity_logs', ['event' => 'documents.updated']);
        $this->assertSame(0, ApplicantDocument::count());
    }

    public function test_required_documents_cannot_be_marked_tidak_ada_but_conditional_can(): void
    {
        $a = $this->makeApplicant();

        $this->submit($a, $this->docs(['akta_kelahiran' => 'tidak_ada']))->assertSessionHasErrors('docs.akta_kelahiran.status');
        $this->submit($a, $this->docs(['foto' => 'tidak_ada']))->assertSessionHasErrors('docs.foto.status');
        $this->submit($a, $this->docs(['kks' => 'tidak_ada', 'ktp_ayah' => 'tidak_ada']))->assertSessionHasNoErrors();
        $this->submit($a, $this->docs(['kks' => 'ngawur']))->assertSessionHasErrors('docs.kks.status');
    }

    public function test_accepted_kks_pkh_and_kip_require_and_save_card_numbers(): void
    {
        $a = $this->makeApplicant();

        $this->submit($a, $this->docs(['kks' => 'diterima', 'pkh' => 'diterima']))
            ->assertSessionHasErrors(['docs.kks.card_number', 'docs.pkh.card_number']);

        $this->submit($a, $this->docs([
            'kks' => ['status' => 'diterima', 'card_number' => '1234 5678'],
            'pkh' => ['status' => 'diterima', 'card_number' => '9876 5432'],
        ]))->assertSessionHasNoErrors();

        $this->assertSame('12345678', ApplicantDocument::where('applicant_id', $a->id)->where('type', 'kks')->value('card_number'));
        $this->assertSame('98765432', ApplicantDocument::where('applicant_id', $a->id)->where('type', 'pkh')->value('card_number'));

        $this->actingAs($this->user)->get(route('applicants.show', $a))
            ->assertOk()
            ->assertSee('Kartu KKS/PKH')
            ->assertSee('Kartu KIP')
            ->assertSee('12345678')
            ->assertSee('98765432');

        $this->submit($a, $this->docs(['kks' => 'tidak_ada', 'pkh' => 'tidak_ada']))
            ->assertSessionHasNoErrors();
        $this->assertNull(ApplicantDocument::where('applicant_id', $a->id)->where('type', 'kks')->value('card_number'));
        $this->assertNull(ApplicantDocument::where('applicant_id', $a->id)->where('type', 'pkh')->value('card_number'));
    }

    // ---------- data KK ----------

    public function test_kk_diterima_requires_kk_data(): void
    {
        $a = $this->makeApplicant();

        $this->submit($a, $this->docs(['kk' => 'diterima']))->assertSessionHasErrors([
            'family.number', 'family.student_nik', 'family.father_nik', 'family.father_birth_year',
            'family.father_occupation_id', 'family.mother_nik', 'family.mother_birth_year', 'family.mother_occupation_id',
        ]);

        $this->assertSame(0, ApplicantDocument::count());   // tidak ada yang tersimpan
        $this->assertSame(0, ApplicantFamilyDetail::count());
    }

    public function test_kk_data_is_saved_and_validated(): void
    {
        $a = $this->makeApplicant();

        $this->submit($a, $this->docs(['kk' => 'diterima']), ['family' => $this->family(['number' => '123'])])
            ->assertSessionHasErrors('family.number');
        $this->submit($a, $this->docs(['kk' => 'diterima']), ['family' => $this->family(['student_nik' => '33190145011000'])])
            ->assertSessionHasErrors('family.student_nik');

        $this->submit($a, $this->docs(['kk' => 'diterima']), ['family' => $this->family(['number' => '3319 0101 0101 0001'])])
            ->assertSessionHasNoErrors();

        $detail = $a->fresh()->familyDetail;
        $this->assertSame('3319010101010001', $detail->kk_number);
        $this->assertSame($this->year->id, $detail->academic_year_id);
        $this->assertSame(1980, $detail->father_birth_year);
        $this->assertDatabaseHas('activity_logs', ['event' => 'family.created', 'subject_id' => $a->id]);
    }

    public function test_parent_birth_year_must_be_reasonable(): void
    {
        $a = $this->makeApplicant(); // lahir 2010

        foreach (['1900', '2005', '2000'] as $year) {
            $this->submit($a, $this->docs(['kk' => 'diterima']), ['family' => $this->family(['father_birth_year' => $year])])
                ->assertSessionHasErrors('family.father_birth_year');
        }
        $this->submit($a, $this->docs(['kk' => 'diterima']), ['family' => $this->family(['father_birth_year' => '1998'])])
            ->assertSessionHasNoErrors();
    }

    public function test_student_nik_is_unique_per_year_even_for_deleted_but_not_across_years(): void
    {
        $first = $this->makeApplicant(1);
        $second = $this->makeApplicant(2);
        $this->submit($first, $this->docs(['kk' => 'diterima']), ['family' => $this->family()])->assertSessionHasNoErrors();

        $this->submit($second, $this->docs(['kk' => 'diterima']), ['family' => $this->family()])
            ->assertSessionHasErrors('family.student_nik');

        $first->delete();
        $this->submit($second, $this->docs(['kk' => 'diterima']), ['family' => $this->family()])
            ->assertSessionHasErrors('family.student_nik');

        // tahun ajaran lain: boleh
        $archived = AcademicYear::create(['name' => '2025/2026', 'registration_fee' => 0, 'uniform_threshold_percent' => 0, 'is_active' => false]);
        ApplicantFamilyDetail::where('applicant_id', $first->id)->update(['academic_year_id' => $archived->id]);
        $this->submit($second, $this->docs(['kk' => 'diterima']), ['family' => $this->family()])->assertSessionHasNoErrors();
    }

    public function test_same_kk_number_is_allowed_with_note_for_siblings(): void
    {
        $a = $this->makeApplicant(1);
        $b = $this->makeApplicant(2);
        $this->submit($a, $this->docs(['kk' => 'diterima']), ['family' => $this->family()]);

        $this->submit($b, $this->docs(['kk' => 'diterima']), ['family' => $this->family(['student_nik' => '3319014501120002', 'father_name' => 'Ayah 2', 'mother_name' => 'Ibu 2'])])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'A-001') && str_contains($m, 'saudara kandung'));
    }

    public function test_parent_names_corrected_from_kk_update_applicant_and_person_key(): void
    {
        $a = $this->makeApplicant();
        $oldKey = $a->person_key;

        $this->submit($a, $this->docs(['kk' => 'diterima']), ['family' => $this->family(['mother_name' => 'Ibu Satu Sesuai KK'])])
            ->assertSessionHasNoErrors();

        $a->refresh();
        $this->assertSame('Ibu Satu Sesuai KK', $a->mother_name);
        $this->assertNotSame($oldKey, $a->person_key);
        $this->assertSame(Applicant::personKey('Murid 1', '2010-01-01', 'Ibu Satu Sesuai KK'), $a->person_key);
        $log = \App\Models\ActivityLog::where('event', 'applicant.updated')->firstOrFail();
        $this->assertSame('Ibu 1', $log->old_values['mother_name']);
    }

    public function test_absent_parent_skips_nik_year_and_occupation(): void
    {
        $a = $this->makeApplicant();

        $this->submit($a, $this->docs(['kk' => 'diterima']), ['family' => $this->family([
            'father_absent' => '1', 'father_nik' => null, 'father_birth_year' => null, 'father_occupation_id' => null,
        ])])->assertSessionHasNoErrors();

        $detail = $a->fresh()->familyDetail;
        $this->assertTrue($detail->father_absent);
        $this->assertNull($detail->father_nik);
        $this->assertFalse($detail->mother_absent);

        // ibu tidak ada tetapi data ibu diisi: diabaikan, tidak tersimpan
        $this->submit($a, $this->docs(['kk' => 'diterima']), ['family' => $this->family(['mother_absent' => '1'])])->assertSessionHasNoErrors();
        $this->assertNull($a->fresh()->familyDetail->mother_nik);
    }

    public function test_guardian_required_when_both_parents_absent_and_optional_when_one(): void
    {
        $a = $this->makeApplicant();
        $both = $this->family(['father_absent' => '1', 'mother_absent' => '1']);

        $this->submit($a, $this->docs(['kk' => 'diterima']), ['family' => $both])->assertSessionHasErrors([
            'family.guardian_name', 'family.guardian_nik', 'family.guardian_relation', 'family.guardian_occupation_id',
        ]);

        $guardian = ['guardian_name' => 'Pak Dhe', 'guardian_nik' => '3319010101700009', 'guardian_relation' => 'Paman',
            'guardian_occupation_id' => $this->job->id, 'guardian_phone' => '081234567890'];
        $this->submit($a, $this->docs(['kk' => 'diterima']), ['family' => $both + $guardian])->assertSessionHasNoErrors();
        $this->assertSame('Pak Dhe', $a->fresh()->familyDetail->guardian_name);

        // satu orang tua tidak ada: wali opsional
        $one = $this->family(['father_absent' => '1']);
        $this->submit($a, $this->docs(['kk' => 'diterima']), ['family' => $one])->assertSessionHasNoErrors();
        $this->assertNull($a->fresh()->familyDetail->guardian_name);

        // dan bila diisi sebagian, harus lengkap
        $this->submit($a, $this->docs(['kk' => 'diterima']), ['family' => $one + ['guardian_name' => 'Kakek']])
            ->assertSessionHasErrors(['family.guardian_nik', 'family.guardian_relation']);
    }

    public function test_guardian_is_cleared_when_both_parents_present(): void
    {
        $a = $this->makeApplicant();
        $guardian = ['guardian_name' => 'Pak Dhe', 'guardian_nik' => '3319010101700009', 'guardian_relation' => 'Paman',
            'guardian_occupation_id' => $this->job->id];
        $this->submit($a, $this->docs(['kk' => 'diterima']), ['family' => $this->family(['father_absent' => '1']) + $guardian]);
        $this->assertSame('Pak Dhe', $a->fresh()->familyDetail->guardian_name);

        $this->submit($a, $this->docs(['kk' => 'diterima']), ['family' => $this->family() + $guardian])->assertSessionHasNoErrors();
        $this->assertNull($a->fresh()->familyDetail->guardian_name);
    }

    public function test_unchecking_kk_keeps_saved_data(): void
    {
        $a = $this->makeApplicant();
        $this->submit($a, $this->docs(['kk' => 'diterima']), ['family' => $this->family()])->assertSessionHasNoErrors();

        $this->submit($a, $this->docs(['kk' => 'belum']))->assertSessionHasNoErrors();

        $this->assertSame(S::Belum, ApplicantDocument::where('type', 'kk')->first()->status);
        $this->assertSame('3319010101010001', $a->fresh()->familyDetail->kk_number);
    }

    // ---------- NISN ----------

    public function test_skl_diterima_requires_a_10_digit_nisn(): void
    {
        $a = $this->makeApplicant();

        $this->submit($a, $this->docs(['skl' => 'diterima']))->assertSessionHasErrors('nisn');
        $this->submit($a, $this->docs(['skl' => 'diterima']), ['nisn' => '12345'])->assertSessionHasErrors('nisn');
        $this->submit($a, $this->docs(['skl' => 'diterima']), ['nisn' => '123456789012'])->assertSessionHasErrors('nisn');
        $this->assertSame(0, ApplicantDocument::count());
    }

    public function test_nisn_is_saved_unique_and_kept_when_skl_unchecked(): void
    {
        $a = $this->makeApplicant(1);
        $b = $this->makeApplicant(2);

        $this->submit($a, $this->docs(['skl' => 'diterima']), ['nisn' => '0012-345-678'])->assertSessionHasNoErrors();
        $this->assertSame('0012345678', $a->fresh()->nisn);
        $this->assertDatabaseHas('activity_logs', ['event' => 'applicant.nisn_changed', 'subject_id' => $a->id]);

        $this->submit($b, $this->docs(['skl' => 'diterima']), ['nisn' => '0012345678'])->assertSessionHasErrors('nisn');

        $this->submit($a, $this->docs(['skl' => 'belum']))->assertSessionHasNoErrors();
        $this->assertSame('0012345678', $a->fresh()->nisn);
    }

    // ---------- satu orang tua tidak ada ----------

    private function fullDocs(array $override = []): array
    {
        return $this->docs($override + [
            'akta_kelahiran' => 'diterima', 'foto' => 'diterima', 'kk' => 'diterima', 'skl' => 'diterima',
            'sktm' => 'tidak_ada', 'kks' => 'tidak_ada', 'pkh' => 'tidak_ada', 'sertifikat' => 'tidak_ada',
        ]);
    }

    public function test_one_parent_absent_without_guardian_is_complete(): void
    {
        $a = $this->makeApplicant();

        $this->submit($a, $this->fullDocs(['ktp_ayah' => 'tidak_ada', 'ktp_ibu' => 'diterima']), [
            'nisn'   => '0012345678',
            'family' => $this->family(['father_absent' => '1']),
        ])->assertSessionHasNoErrors();

        $this->assertTrue($a->fresh()->documents_complete);
        $this->assertNull($a->fresh()->familyDetail->guardian_name);
    }

    public function test_absent_flag_in_kk_counts_even_if_ktp_status_left_belum(): void
    {
        $a = $this->makeApplicant();

        $this->submit($a, $this->fullDocs(['ktp_ayah' => 'belum', 'ktp_ibu' => 'diterima']), [
            'nisn'   => '0012345678',
            'family' => $this->family(['father_absent' => '1']),
        ])->assertSessionHasNoErrors();

        $this->assertTrue($a->fresh()->documents_complete);
    }

    public function test_one_parent_present_but_other_undecided_is_incomplete(): void
    {
        $a = $this->makeApplicant();

        $this->submit($a, $this->fullDocs(['ktp_ayah' => 'belum', 'ktp_ibu' => 'diterima']), [
            'nisn'   => '0012345678',
            'family' => $this->family(),
        ])->assertSessionHasNoErrors();

        $this->assertFalse($a->fresh()->documents_complete);
    }

    public function test_both_parents_absent_needs_guardian_ktp(): void
    {
        $a = $this->makeApplicant();
        $guardian = ['guardian_name' => 'Pak Dhe', 'guardian_nik' => '3319010101700009', 'guardian_relation' => 'Paman',
            'guardian_occupation_id' => $this->job->id];
        $family = $this->family(['father_absent' => '1', 'mother_absent' => '1'] + $guardian);

        $this->submit($a, $this->fullDocs(['ktp_ayah' => 'tidak_ada', 'ktp_ibu' => 'tidak_ada']), ['nisn' => '0012345678', 'family' => $family])
            ->assertSessionHasNoErrors();
        $this->assertFalse($a->fresh()->documents_complete);

        $this->submit($a, $this->fullDocs(['ktp_ayah' => 'tidak_ada', 'ktp_ibu' => 'tidak_ada', 'ktp_wali' => 'diterima']), ['nisn' => '0012345678', 'family' => $family])
            ->assertSessionHasNoErrors();
        $this->assertTrue($a->fresh()->documents_complete);
    }

    public function test_recalculate_command_fixes_stale_completeness(): void
    {
        $a = $this->makeApplicant(1);
        $b = $this->makeApplicant(2);

        foreach ([
            'akta_kelahiran' => 'diterima', 'kk' => 'diterima', 'skl' => 'diterima', 'foto' => 'diterima',
            'ktp_ayah' => 'tidak_ada', 'ktp_ibu' => 'diterima',
            'sktm' => 'tidak_ada', 'kks' => 'tidak_ada', 'pkh' => 'tidak_ada', 'sertifikat' => 'tidak_ada',
        ] as $type => $status) {
            ApplicantDocument::create(['applicant_id' => $a->id, 'type' => $type, 'status' => $status]);
        }
        $this->assertFalse($a->fresh()->documents_complete); // nilai lama yang basi

        $this->artisan('berkas:hitung-ulang')->assertSuccessful();

        $this->assertTrue($a->fresh()->documents_complete);
        $this->assertFalse($b->fresh()->documents_complete);
    }

    // ---------- akses ----------

    public function test_other_roles_cannot_use_document_pages(): void
    {
        $a = $this->makeApplicant();

        foreach ([Role::DaftarUlang, Role::Seragam] as $role) {
            $user = User::factory()->role($role)->create();
            $this->actingAs($user)->get('/berkas')->assertForbidden();
            $this->actingAs($user)->get("/berkas/{$a->id}")->assertForbidden();
            $this->submit($a, $this->docs(['foto' => 'diterima']), [], $user)->assertForbidden();
        }

        $this->assertSame(0, ApplicantDocument::count());
    }

    public function test_admin_can_use_document_pages(): void
    {
        $a = $this->makeApplicant();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/berkas')->assertOk();
        $this->actingAs($admin)->get("/berkas/{$a->id}")->assertOk()->assertSee('Akta kelahiran')->assertSee('Sertifikat kejuaraan');
        $this->submit($a, $this->docs(['foto' => 'diterima']), [], $admin)->assertSessionHasNoErrors();
    }

    public function test_archived_year_and_deleted_applicants_cannot_be_edited(): void
    {
        $archived = AcademicYear::create(['name' => '2025/2026', 'registration_fee' => 0, 'uniform_threshold_percent' => 0, 'is_active' => false]);
        $old = $this->makeApplicant(1, ['academic_year_id' => $archived->id]);
        $gone = $this->makeApplicant(2);
        $gone->delete();

        $this->actingAs($this->user)->get("/berkas/{$old->id}")->assertForbidden();
        $this->submit($old, $this->docs(['foto' => 'diterima']))->assertForbidden();
        $this->actingAs($this->user)->get("/berkas/{$gone->id}")->assertNotFound();
    }

    // ---------- daftar, ringkasan, dashboard ----------

    public function test_index_filters_by_completeness_and_shows_what_is_missing(): void
    {
        $done = $this->makeApplicant(1, ['name' => 'Murid Lengkap', 'documents_complete' => true]);
        $this->makeApplicant(2, ['name' => 'Murid Kurang']);

        $this->actingAs($this->user)->get('/berkas?status=lengkap')->assertSee('Murid Lengkap')->assertDontSee('Murid Kurang');
        $this->actingAs($this->user)->get('/berkas?status=belum')->assertSee('Murid Kurang')->assertDontSee('Murid Lengkap')
            ->assertSee('Akta kelahiran');
        $this->actingAs($this->user)->get('/berkas?q=A-002')->assertSee('Murid Kurang')->assertDontSee('Murid Lengkap');
    }

    public function test_applicant_page_shows_document_summary_and_kk_data(): void
    {
        $a = $this->makeApplicant();
        $this->submit($a, $this->docs(['skl' => 'diterima', 'kk' => 'diterima']), ['nisn' => '0012345678', 'family' => $this->family()]);

        $this->actingAs($this->user)->get("/applicants/{$a->id}")
            ->assertOk()->assertSee('Berkas')->assertSee('0012345678')->assertSee('3319010101010001')->assertSee('Petani/Pekebun');
    }

    public function test_dashboard_shows_document_counts(): void
    {
        $this->makeApplicant(1, ['documents_complete' => true]);
        $this->makeApplicant(2);
        $this->makeApplicant(3);

        $this->actingAs($this->user)->get('/dashboard')->assertOk()->assertSee('Berkas lengkap')->assertSee('Berkas belum lengkap');
    }
}
