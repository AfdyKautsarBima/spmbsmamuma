<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_user_can_login_with_username(): void
    {
        $user = User::factory()->create(['username' => 'budi.s']);

        $this->post('/login', ['username' => 'Budi.S', 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('activity_logs', ['event' => 'login', 'user_id' => $user->id]);
    }

    public function test_wrong_password_is_rejected_and_logged(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['username' => $user->username, 'password' => 'salah'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->assertDatabaseHas('activity_logs', ['event' => 'login_failed']);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', ['username' => $user->username, 'password' => 'password'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_login_is_locked_after_too_many_failures(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => $user->username, 'password' => 'salah']);
        }

        $this->post('/login', ['username' => $user->username, 'password' => 'password'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_there_is_no_public_registration_or_password_reset(): void
    {
        $this->get('/register')->assertNotFound();
        $this->get('/forgot-password')->assertNotFound();
    }

    public function test_deactivated_user_with_open_session_is_kicked_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->update(['is_active' => false]);

        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_activity_log_cannot_be_modified_or_deleted(): void
    {
        $log = ActivityLog::record('test', 'uji');

        $this->expectException(\LogicException::class);
        $log->delete();
    }

    public function test_activity_log_cannot_be_updated(): void
    {
        $log = ActivityLog::record('test', 'uji');

        $this->expectException(\LogicException::class);
        $log->update(['description' => 'diubah']);
    }
}
