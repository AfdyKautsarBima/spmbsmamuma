<?php

namespace Tests\Feature;

use App\Models\Referrer;
use App\Models\User;
use App\Services\ReferrerMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferrerTest extends TestCase
{
    use RefreshDatabase;

    public function test_key_ignores_case_spaces_punctuation_and_honorifics(): void
    {
        $m = new ReferrerMatcher();

        $this->assertSame('budi santoso', $m->key('Budi   Santoso'));
        $this->assertSame('budi santoso', $m->key('H. Budi Santoso'));
        $this->assertSame('budi santoso', $m->key('Drs. H. BUDI SANTOSO, S.Pd.'));
        $this->assertSame('budi', $m->key('Pak Budi'));
        $this->assertSame('pak', $m->key('Pak')); // tidak boleh menjadi kosong
    }

    public function test_clean_name_title_cases_and_collapses_spaces(): void
    {
        $this->assertSame('Budi Santoso', Referrer::cleanName('  budi   SANTOSO '));
    }

    public function test_panitia_pendaftaran_can_add_referrer_via_json(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/referrers', ['name' => 'budi santoso'])
            ->assertCreated()
            ->assertJson(['name' => 'Budi Santoso']);

        $this->assertDatabaseHas('referrers', ['name' => 'Budi Santoso', 'name_key' => 'budi santoso', 'created_by' => $user->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'referrer.created']);
    }

    public function test_exact_duplicate_after_normalization_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/referrers', ['name' => 'Budi Santoso'])->assertCreated();

        $this->actingAs($user)->postJson('/referrers', ['name' => 'H. BUDI  santoso'])
            ->assertStatus(422)
            ->assertJsonPath('existing.name', 'Budi Santoso');

        $this->assertSame(1, Referrer::count());
    }

    public function test_similar_name_requires_confirmation_then_can_be_forced(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/referrers', ['name' => 'Budi Santoso'])->assertCreated();

        $this->actingAs($user)->postJson('/referrers', ['name' => 'Budi'])
            ->assertStatus(409)
            ->assertJson(['requires_confirmation' => true])
            ->assertJsonPath('similar.0.name', 'Budi Santoso');
        $this->assertSame(1, Referrer::count());

        $this->actingAs($user)->postJson('/referrers', ['name' => 'Budi', 'force' => true])->assertCreated();
        $this->assertSame(2, Referrer::count());
    }

    public function test_typo_is_flagged_as_similar(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/referrers', ['name' => 'Ahmad Fauzi'])->assertCreated();

        $this->actingAs($user)->postJson('/referrers', ['name' => 'Ahmad Fauzy'])->assertStatus(409);
        $this->actingAs($user)->postJson('/referrers', ['name' => 'Siti Aminah'])->assertCreated();
    }

    public function test_name_validation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/referrers', ['name' => ''])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->actingAs($user)->postJson('/referrers', ['name' => '12345'])->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_search_returns_matches_and_nothing_for_empty_query(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/referrers', ['name' => 'Budi Santoso']);
        $this->actingAs($user)->postJson('/referrers', ['name' => 'Siti Aminah']);

        $this->actingAs($user)->getJson('/referrers/search?q=budi')->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Budi Santoso');
        $this->actingAs($user)->getJson('/referrers/search?q=')->assertOk()->assertExactJson([]);
    }

    public function test_admin_can_rename_and_it_is_logged_but_cannot_rename_into_duplicate(): void
    {
        $admin = User::factory()->admin()->create();
        $a = Referrer::create(['name' => 'Budi Santoso', 'name_key' => 'budi santoso']);
        Referrer::create(['name' => 'Siti Aminah', 'name_key' => 'siti aminah']);

        $this->actingAs($admin)->put("/referrers/{$a->id}", ['name' => 'Budi Santosa Putra'])->assertRedirect('/referrers');
        $this->assertSame('Budi Santosa Putra', $a->fresh()->name);
        $this->assertDatabaseHas('activity_logs', ['event' => 'referrer.renamed']);

        $this->actingAs($admin)->put("/referrers/{$a->id}", ['name' => 'siti aminah'])->assertSessionHas('error');
        $this->assertSame('Budi Santosa Putra', $a->fresh()->name);
    }

    public function test_rename_to_own_normalized_name_is_not_a_duplicate(): void
    {
        $admin = User::factory()->admin()->create();
        $a = Referrer::create(['name' => 'Budi Santoso', 'name_key' => 'budi santoso']);

        $this->actingAs($admin)->put("/referrers/{$a->id}", ['name' => 'H. Budi Santoso'])->assertRedirect('/referrers');
        $this->assertSame('H. Budi Santoso', $a->fresh()->name);
    }

    public function test_admin_page_shows_confirmation_banner_for_similar_name(): void
    {
        $admin = User::factory()->admin()->create();
        Referrer::create(['name' => 'Budi Santoso', 'name_key' => 'budi santoso']);

        $this->actingAs($admin)->from('/referrers')->post('/referrers', ['name' => 'Budi'])->assertRedirect('/referrers');
        $this->get('/referrers')->assertSee('Tetap simpan');
    }

    public function test_other_roles_cannot_use_referrer_endpoints(): void
    {
        foreach ([\App\Enums\Role::DaftarUlang, \App\Enums\Role::Seragam] as $role) {
            $this->actingAs(User::factory()->role($role)->create());
            $this->postJson('/referrers', ['name' => 'Budi'])->assertForbidden();
            $this->getJson('/referrers/search?q=a')->assertForbidden();
        }

        $this->actingAs(User::factory()->create())->get('/referrers')->assertForbidden(); // daftar admin
    }
}
