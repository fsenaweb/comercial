<?php

namespace Tests\Feature\Unit;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_units(): void
    {
        $this->getJson('/api/units')->assertStatus(401);
    }

    public function test_authenticated_user_can_list_units(): void
    {
        Unit::factory()->create(['name' => 'Unidade', 'abbreviation' => 'UN']);
        Unit::factory()->create(['name' => 'Caixa', 'abbreviation' => 'CX']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/units');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_admin_can_create_unit(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/units', [
            'name' => 'Caixa',
            'abbreviation' => 'CX',
        ]);

        $response->assertCreated()->assertJsonPath('data.abbreviation', 'CX');
        $this->assertDatabaseHas('units', ['name' => 'Caixa', 'abbreviation' => 'CX']);
    }

    public function test_seller_cannot_create_unit(): void
    {
        $seller = User::factory()->create();

        $response = $this->actingAs($seller)->postJson('/api/units', [
            'name' => 'Caixa',
            'abbreviation' => 'CX',
        ]);

        $response->assertStatus(403);
    }

    public function test_create_requires_abbreviation(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/units', ['name' => 'Caixa']);

        $response->assertStatus(422)->assertJsonValidationErrors(['abbreviation']);
    }

    public function test_admin_can_update_unit(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = Unit::factory()->create();

        $response = $this->actingAs($admin)->putJson("/api/units/{$unit->id}", [
            'name' => 'Atualizada',
            'abbreviation' => 'AT',
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'Atualizada');
    }

    public function test_admin_can_delete_unit(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = Unit::factory()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/units/{$unit->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('units', ['id' => $unit->id]);
    }
}
