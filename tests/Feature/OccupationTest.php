<?php

namespace Tests\Feature;

use App\Models\Occupation;
use App\Models\User;
use Database\Seeders\OccupationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OccupationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_is_idempotent_and_lainnya_is_last(): void
    {
        $this->seed(OccupationSeeder::class);
        $count = Occupation::count();
        $this->seed(OccupationSeeder::class);

        $this->assertSame($count, Occupation::count());
        $this->assertGreaterThan(30, $count);
        $this->assertSame('Lainnya', Occupation::ordered()->get()->last()->name);
    }

    public function test_admin_can_add_edit_and_toggle(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/occupations', ['name' => ' Petani   Garam '])->assertRedirect('/occupations');
        $occupation = Occupation::where('name', 'Petani Garam')->firstOrFail();
        $this->assertSame(1000, $occupation->sort_order);

        $this->actingAs($admin)->put("/occupations/{$occupation->id}", ['name' => 'Petambak Garam', 'sort_order' => 55])
            ->assertRedirect('/occupations');
        $this->assertSame('Petambak Garam', $occupation->fresh()->name);
        $this->assertSame(55, $occupation->fresh()->sort_order);

        $this->actingAs($admin)->patch("/occupations/{$occupation->id}/toggle-active");
        $this->assertFalse($occupation->fresh()->is_active);
        $this->assertSame(0, Occupation::active()->where('id', $occupation->id)->count());

        $this->assertDatabaseHas('activity_logs', ['event' => 'occupation.created']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'occupation.updated']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'occupation.deactivated']);
    }

    public function test_name_must_be_unique(): void
    {
        $admin = User::factory()->admin()->create();
        Occupation::create(['name' => 'Guru']);

        $this->actingAs($admin)->post('/occupations', ['name' => 'Guru'])->assertSessionHasErrors('name');
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())->get('/occupations')->assertForbidden();
    }
}
