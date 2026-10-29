<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalize_code(): void
    {
        $this->assertSame('33', Region::normalizeCode('33'));
        $this->assertSame('33.19', Region::normalizeCode('3319'));
        $this->assertSame('33.19.01', Region::normalizeCode('33.19.01'));
        $this->assertSame('33.19.01.2001', Region::normalizeCode('3319012001'));
        $this->assertSame('33.19.01.2001', Region::normalizeCode('33.19.01.2001'));
        $this->assertNull(Region::normalizeCode('kode'));
        $this->assertNull(Region::normalizeCode('331'));
        $this->assertNull(Region::normalizeCode(''));
    }

    public function test_import_from_csv_with_header_and_extra_columns(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'wil').'.csv';
        file_put_contents($path, implode("\n", [
            'id,parent_id,name',
            '33,,JAWA TENGAH',
            '3319,33,KABUPATEN KUDUS',
            '331901,3319,KALIWUNGU',
            '3319012001,331901,BAKALAN KRAPYAK',
        ]));

        $this->artisan('wilayah:import', ['file' => $path])->assertSuccessful();

        $this->assertSame(4, Region::count());
        $village = Region::find('33.19.01.2001');
        $this->assertSame('BAKALAN KRAPYAK', $village->name);
        $this->assertSame(4, $village->level);
        $this->assertSame('33.19.01', $village->parent_code);
        $this->assertNull(Region::find('33')->parent_code);
    }

    public function test_import_from_cahyadsn_sql_dump_and_unescape_quotes(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'wil').'.sql';
        file_put_contents($path, <<<'SQL'
        INSERT INTO `wilayah` (`kode`, `nama`) VALUES
        ('33', 'JAWA TENGAH'),
        ('33.19', 'KAB. KUDUS'),
        ('33.19.01', 'KALIWUNGU'),
        ('33.19.01.2001', 'DESA O''BRIEN');
        SQL);

        $this->artisan('wilayah:import', ['file' => $path])->assertSuccessful();

        $this->assertSame(4, Region::count());
        $this->assertSame("DESA O'BRIEN", Region::find('33.19.01.2001')->name);
    }

    public function test_import_twice_updates_without_duplicates_and_replace_clears(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'wil').'.csv';
        file_put_contents($path, "33,JAWA TENGAH\n3319,KAB. KUDUS\n");
        $this->artisan('wilayah:import', ['file' => $path])->assertSuccessful();

        file_put_contents($path, "33,JAWA TENGAH\n3320,KAB. JEPARA\n");
        $this->artisan('wilayah:import', ['file' => $path])->assertSuccessful();
        $this->assertSame(3, Region::count());

        $this->artisan('wilayah:import', ['file' => $path, '--replace' => true])->assertSuccessful();
        $this->assertSame(2, Region::count());
    }

    public function test_import_fails_on_missing_or_unreadable_content(): void
    {
        $this->artisan('wilayah:import', ['file' => '/tidak/ada.csv'])->assertFailed();

        $path = tempnam(sys_get_temp_dir(), 'wil').'.csv';
        file_put_contents($path, "isi,tidak,relevan\n");
        $this->artisan('wilayah:import', ['file' => $path])->assertFailed();
    }

    public function test_children_endpoint_lists_provinces_and_puts_priority_regencies_first(): void
    {
        foreach ([['33', 'JAWA TENGAH', 1, null], ['32', 'JAWA BARAT', 1, null],
                  ['33.01', 'KAB. CILACAP', 2, '33'], ['33.02', 'KAB. BANYUMAS', 2, '33'],
                  ['33.19', 'KAB. KUDUS', 2, '33'], ['33.20', 'KAB. JEPARA', 2, '33']] as [$code, $name, $level, $parent]) {
            Region::create(['code' => $code, 'name' => $name, 'level' => $level, 'parent_code' => $parent]);
        }

        $user = User::factory()->create(); // panitia pendaftaran

        $provinces = $this->actingAs($user)->getJson('/wilayah')->assertOk()->json();
        $this->assertSame(['32', '33'], array_column($provinces, 'code'));

        $regencies = $this->actingAs($user)->getJson('/wilayah?parent=33')->assertOk()->json();
        $this->assertSame(['33.20', '33.19', '33.02', '33.01'], array_column($regencies, 'code'));
    }

    public function test_children_endpoint_rejects_garbage_parent(): void
    {
        $this->actingAs(User::factory()->create())->getJson("/wilayah?parent=abc'--")->assertOk()->assertExactJson([]);
    }

    public function test_children_endpoint_is_forbidden_for_other_roles(): void
    {
        $this->actingAs(User::factory()->role(\App\Enums\Role::Seragam)->create())->getJson('/wilayah')->assertForbidden();
    }

    public function test_admin_region_page_shows_picker_and_config_names(): void
    {
        Region::create(['code' => '33', 'name' => 'JAWA TENGAH', 'level' => 1]);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/master/wilayah')
            ->assertOk()
            ->assertSee('data-region-picker', false)
            ->assertSee('JAWA TENGAH');
    }
}
