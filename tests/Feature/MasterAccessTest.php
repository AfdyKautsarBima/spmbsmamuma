<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_all_master_pages(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        foreach (['/master', '/master/wilayah', '/schools', '/schools/create', '/referrers', '/occupations', '/occupations/create'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_non_admin_cannot_open_master_pages(): void
    {
        foreach ([Role::Pendaftaran, Role::DaftarUlang, Role::Seragam] as $role) {
            $this->actingAs(User::factory()->role($role)->create());

            foreach (['/master', '/master/wilayah', '/schools', '/occupations'] as $url) {
                $this->get($url)->assertForbidden();
            }
        }
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/master')->assertRedirect('/login');
        $this->getJson('/wilayah')->assertUnauthorized();
    }
}
