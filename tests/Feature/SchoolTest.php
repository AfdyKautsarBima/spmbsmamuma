<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Region;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class SchoolTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Region::create(['code' => '33', 'name' => 'JAWA TENGAH', 'level' => 1]);
        Region::create(['code' => '33.19', 'name' => 'KAB. KUDUS', 'level' => 2, 'parent_code' => '33']);
        Region::create(['code' => '33.20', 'name' => 'KAB. JEPARA', 'level' => 2, 'parent_code' => '33']);

        return User::factory()->admin()->create();
    }

    public function test_admin_can_create_school_and_regency_name_is_copied(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/schools', [
            'name' => '  SMP Negeri   1 Kudus ', 'npsn' => '20300001', 'regency_code' => '33.19',
        ])->assertRedirect('/schools');

        $school = School::first();
        $this->assertSame('SMP Negeri 1 Kudus', $school->name);
        $this->assertSame('KAB. KUDUS', $school->regency_name);
        $this->assertTrue($school->is_active);
        $this->assertDatabaseHas('activity_logs', ['event' => 'school.created', 'user_id' => $admin->id]);
    }

    public function test_npsn_is_optional_but_must_be_8_digits_and_unique(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/schools', ['name' => 'SMP A', 'regency_code' => '33.19'])->assertRedirect('/schools');
        $this->actingAs($admin)->post('/schools', ['name' => 'SMP B', 'regency_code' => '33.19'])->assertRedirect('/schools');
        $this->assertSame(2, School::count());

        $this->actingAs($admin)->post('/schools', ['name' => 'SMP C', 'npsn' => '123', 'regency_code' => '33.19'])
            ->assertSessionHasErrors('npsn');

        $this->actingAs($admin)->post('/schools', ['name' => 'SMP C', 'npsn' => '20300001', 'regency_code' => '33.19']);
        $this->actingAs($admin)->post('/schools', ['name' => 'SMP D', 'npsn' => '20300001', 'regency_code' => '33.19'])
            ->assertSessionHasErrors('npsn');
    }

    public function test_same_name_is_allowed_in_different_regency_but_not_in_same(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/schools', ['name' => 'SMP Negeri 1', 'regency_code' => '33.19'])->assertRedirect('/schools');
        $this->actingAs($admin)->post('/schools', ['name' => 'SMP Negeri 1', 'regency_code' => '33.20'])->assertRedirect('/schools');
        $this->actingAs($admin)->post('/schools', ['name' => 'SMP Negeri 1', 'regency_code' => '33.19'])
            ->assertSessionHasErrors('name');
    }

    public function test_regency_must_be_a_real_regency_level_region(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/schools', ['name' => 'SMP X', 'regency_code' => '33'])->assertSessionHasErrors('regency_code');
        $this->actingAs($admin)->post('/schools', ['name' => 'SMP X', 'regency_code' => '99.99'])->assertSessionHasErrors('regency_code');
        $this->actingAs($admin)->post('/schools', ['name' => 'SMP X'])->assertSessionHasErrors('regency_code');
    }

    public function test_update_can_keep_own_npsn_and_logs_changes(): void
    {
        $admin = $this->admin();
        $school = School::create(['name' => 'SMP A', 'npsn' => '20300001', 'regency_code' => '33.19', 'regency_name' => 'KAB. KUDUS']);

        $this->actingAs($admin)->put("/schools/{$school->id}", [
            'name' => 'SMP A Baru', 'npsn' => '20300001', 'regency_code' => '33.20',
        ])->assertRedirect('/schools');

        $school->refresh();
        $this->assertSame('SMP A Baru', $school->name);
        $this->assertSame('KAB. JEPARA', $school->regency_name);
        $this->assertDatabaseHas('activity_logs', ['event' => 'school.updated']);
    }

    public function test_deactivate_and_activate(): void
    {
        $admin = $this->admin();
        $school = School::create(['name' => 'SMP A', 'regency_code' => '33.19', 'regency_name' => 'KAB. KUDUS']);

        $this->actingAs($admin)->patch("/schools/{$school->id}/toggle-active")->assertSessionHas('success');
        $this->assertFalse($school->fresh()->is_active);
        $this->assertSame(0, School::active()->count());

        $this->actingAs($admin)->patch("/schools/{$school->id}/toggle-active");
        $this->assertTrue($school->fresh()->is_active);
    }

    public function test_admin_can_import_schools_from_excel(): void
    {
        $admin = $this->admin();
        $file = UploadedFile::fake()->createWithContent('sekolah.xlsx', $this->xlsx([
            ['Nama Sekolah', 'NPSN', 'Kode Kabupaten/Kota'],
            ['SMP Negeri 1 Kudus', '20300001', '3319'],
            ['SMP Negeri 2 Jepara', '', '33.20'],
        ], true));

        $this->actingAs($admin)->post('/schools/import', ['file' => $file])
            ->assertRedirect('/schools')
            ->assertSessionHas('success', '2 sekolah berhasil diimpor.');

        $this->assertDatabaseHas('schools', [
            'name' => 'SMP Negeri 1 Kudus',
            'npsn' => '20300001',
            'regency_code' => '33.19',
            'regency_name' => 'KAB. KUDUS',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('schools', [
            'name' => 'SMP Negeri 2 Jepara',
            'npsn' => null,
            'regency_code' => '33.20',
            'regency_name' => 'KAB. JEPARA',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'schools.imported',
            'user_id' => $admin->id,
        ]);
    }

    public function test_invalid_excel_import_does_not_save_any_school(): void
    {
        $this->admin();
        $file = UploadedFile::fake()->createWithContent('sekolah.xlsx', $this->xlsx([
            ['Nama Sekolah', 'NPSN', 'Kode Kabupaten/Kota'],
            ['SMP Valid', '20300001', '33.19'],
            ['SMP Invalid', '123', '99.99'],
        ]));

        $this->actingAs(User::first())->post('/schools/import', ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, School::count());
        $this->assertDatabaseMissing('activity_logs', ['event' => 'schools.imported']);
    }

    public function test_malformed_excel_file_returns_validation_error(): void
    {
        $admin = $this->admin();
        $file = UploadedFile::fake()->createWithContent('sekolah.xlsx', 'bukan file Excel');

        $this->actingAs($admin)->post('/schools/import', ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, School::count());
    }

    public function test_admin_can_download_excel_import_template(): void
    {
        $response = $this->actingAs($this->admin())->get('/schools/import-template')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('content-disposition', 'attachment; filename="template-import-sekolah.xlsx"');

        $this->assertStringStartsWith('PK', $response->getContent());
        $path = tempnam(sys_get_temp_dir(), 'template');
        file_put_contents($path, $response->getContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);

        $this->assertStringContainsString('Nama Sekolah', $sheet);
        $this->assertStringContainsString('NPSN', $sheet);
        $this->assertStringContainsString('Kode Kabupaten/Kota', $sheet);
    }

    public function test_only_admin_can_manage_schools(): void
    {
        foreach ([Role::Pendaftaran, Role::DaftarUlang, Role::Seragam] as $role) {
            $this->actingAs(User::factory()->role($role)->create());
            $this->get('/schools')->assertForbidden();
            $this->post('/schools', ['name' => 'X'])->assertForbidden();
            $this->post('/schools/import')->assertForbidden();
        }
    }

    /** @param list<list<string>> $rows */
    private function xlsx(array $rows, bool $sharedStrings = false): string
    {
        $path = tempnam(sys_get_temp_dir(), 'schools');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sekolah" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');

        $sheetRows = '';
        $strings = [];
        foreach ($rows as $rowNumber => $row) {
            $cells = '';
            foreach ($row as $column => $value) {
                $letter = chr(65 + $column);
                $text = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                if ($sharedStrings) {
                    $index = count($strings);
                    $strings[] = "<si><t>{$text}</t></si>";
                    $cells .= "<c r=\"{$letter}".($rowNumber + 1)."\" t=\"s\"><v>{$index}</v></c>";
                } else {
                    $cells .= "<c r=\"{$letter}".($rowNumber + 1)."\" t=\"inlineStr\"><is><t>{$text}</t></is></c>";
                }
            }
            $number = $rowNumber + 1;
            $sheetRows .= "<row r=\"{$number}\">{$cells}</row>";
        }
        if ($sharedStrings) {
            $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($strings).'" uniqueCount="'.count($strings).'">'.implode('', $strings).'</sst>');
        }
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>');
        $zip->close();

        $content = file_get_contents($path);
        unlink($path);

        return $content;
    }
}
