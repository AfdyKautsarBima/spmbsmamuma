<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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

    public function test_only_admin_can_manage_schools(): void
    {
        foreach ([\App\Enums\Role::Pendaftaran, \App\Enums\Role::DaftarUlang, \App\Enums\Role::Seragam] as $role) {
            $this->actingAs(User::factory()->role($role)->create());
            $this->get('/schools')->assertForbidden();
            $this->post('/schools', ['name' => 'X'])->assertForbidden();
        }
    }
}
