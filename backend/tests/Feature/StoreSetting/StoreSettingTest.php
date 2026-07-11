<?php

namespace Tests\Feature\StoreSetting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_store_settings(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/store-settings');

        $response->assertOk()->assertJsonStructure([
            'data' => ['name', 'cnpj', 'address', 'phone', 'require_seller_on_sale', 'auto_open_cash_register'],
        ]);

        // Provisionamento implícito do registro único não pode deixar os
        // booleanos como null (save() não recarrega defaults de coluna).
        $response->assertJsonPath('data.require_seller_on_sale', false)
            ->assertJsonPath('data.auto_open_cash_register', false);
    }

    public function test_guest_cannot_view_store_settings(): void
    {
        $this->getJson('/api/store-settings')->assertStatus(401);
    }

    public function test_admin_can_update_store_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->putJson('/api/store-settings', [
            'name' => 'Loja do Zé',
            'require_seller_on_sale' => true,
            'auto_open_cash_register' => false,
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'Loja do Zé');
        $this->assertDatabaseHas('store_settings', ['id' => 1, 'name' => 'Loja do Zé']);
    }

    public function test_seller_cannot_update_store_settings(): void
    {
        $seller = User::factory()->create();

        $response = $this->actingAs($seller)->putJson('/api/store-settings', [
            'name' => 'Loja do Zé',
            'require_seller_on_sale' => true,
            'auto_open_cash_register' => false,
        ]);

        $response->assertStatus(403);
    }

    public function test_update_requires_name(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->putJson('/api/store-settings', [
            'require_seller_on_sale' => true,
            'auto_open_cash_register' => false,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['name']);
    }
}
