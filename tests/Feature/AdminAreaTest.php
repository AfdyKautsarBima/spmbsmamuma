<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAreaTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_roles_cannot_open_admin_pages(): void
    {
        foreach ([Role::Pendaftaran, Role::DaftarUlang, Role::Seragam] as $role) {
            $user = User::factory()->role($role)->create();

            $this->actingAs($user)->get('/users')->assertForbidden();
            $this->actingAs($user)->get('/academic-years')->assertForbidden();
            $this->actingAs($user)->get('/activity-logs')->assertForbidden();
            $this->actingAs($user)->get('/dashboard')->assertOk();
        }
    }

    public function test_admin_can_create_user_and_it_is_logged(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/users', [
            'name' => 'Siti', 'username' => 'Siti.A', 'role' => 'daftar_ulang', 'password' => 'rahasia123',
        ])->assertRedirect('/users');

        $this->assertDatabaseHas('users', ['username' => 'siti.a', 'role' => 'daftar_ulang', 'is_active' => true]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'user.created', 'user_id' => $admin->id]);
    }

    public function test_username_must_be_unique_and_valid(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['username' => 'ada']);

        $this->actingAs($admin)->post('/users', [
            'name' => 'X', 'username' => 'ada', 'role' => 'seragam', 'password' => 'rahasia123',
        ])->assertSessionHasErrors('username');

        $this->actingAs($admin)->post('/users', [
            'name' => 'X', 'username' => 'ada baru!', 'role' => 'seragam', 'password' => 'rahasia123',
        ])->assertSessionHasErrors('username');
    }

    public function test_admin_cannot_deactivate_or_demote_self(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch("/users/{$admin->id}/toggle-active")->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->is_active);

        $this->actingAs($admin)->put("/users/{$admin->id}", [
            'name' => $admin->name, 'username' => $admin->username, 'role' => 'seragam',
        ])->assertSessionHasErrors('role');
        $this->assertSame(Role::Admin, $admin->fresh()->role);
    }

    public function test_admin_can_reset_password_of_another_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->put("/users/{$user->id}", [
            'name' => $user->name, 'username' => $user->username, 'role' => $user->role->value, 'password' => 'baru12345',
        ])->assertRedirect('/users');

        $this->assertDatabaseHas('activity_logs', ['event' => 'user.password_reset']);
        $this->post('/logout');
        $this->post('/login', ['username' => $user->username, 'password' => 'baru12345'])->assertRedirect('/dashboard');
    }

    public function test_first_academic_year_is_active_and_activation_switches(): void
    {
        $admin = User::factory()->admin()->create();
        $payload = fn (string $name) => ['name' => $name, 'registration_fee' => 1500000, 'uniform_threshold_percent' => 60];

        $this->actingAs($admin)->post('/academic-years', $payload('2026/2027'))->assertRedirect();
        $this->actingAs($admin)->post('/academic-years', $payload('2027/2028'))->assertRedirect();

        $first = AcademicYear::where('name', '2026/2027')->first();
        $second = AcademicYear::where('name', '2027/2028')->first();
        $this->assertTrue($first->is_active);
        $this->assertFalse($second->is_active);

        $this->actingAs($admin)->patch("/academic-years/{$second->id}/activate")->assertSessionHas('success');

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
        $this->assertSame(1, AcademicYear::where('is_active', true)->count());
    }

    public function test_academic_year_name_format_is_validated(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/academic-years', [
            'name' => '2026/2028', 'registration_fee' => 0, 'uniform_threshold_percent' => 50,
        ])->assertSessionHasErrors('name');

        $this->actingAs($admin)->post('/academic-years', [
            'name' => '2026/2027', 'registration_fee' => 0, 'uniform_threshold_percent' => 150,
        ])->assertSessionHasErrors('uniform_threshold_percent');
    }

    public function test_changing_fee_is_logged_with_old_and_new_values(): void
    {
        $admin = User::factory()->admin()->create();
        $year = AcademicYear::create(['name' => '2026/2027', 'registration_fee' => 1000000, 'uniform_threshold_percent' => 50, 'is_active' => true]);

        $this->actingAs($admin)->put("/academic-years/{$year->id}", [
            'name' => '2026/2027', 'registration_fee' => 1500000, 'uniform_threshold_percent' => 50,
        ])->assertRedirect();

        $log = \App\Models\ActivityLog::where('event', 'academic_year.updated')->first();
        $this->assertEquals(1000000, $log->old_values['registration_fee']);
        $this->assertEquals(1500000, $log->new_values['registration_fee']);
    }

    public function test_user_can_change_own_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/password', [
            'current_password' => 'password', 'password' => 'passwordbaru1', 'password_confirmation' => 'passwordbaru1',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('activity_logs', ['event' => 'password.changed']);
    }
}
