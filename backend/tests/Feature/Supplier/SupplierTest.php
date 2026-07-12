<?php

namespace Tests\Feature\Supplier;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_suppliers(): void
    {
        $this->getJson('/api/suppliers')->assertStatus(401);
    }

    public function test_authenticated_user_can_list_suppliers(): void
    {
        Supplier::factory()->count(2)->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/suppliers');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_admin_can_create_supplier(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/suppliers', [
            'corporate_name' => 'Distribuidora Central Ltda',
            'is_company' => true,
        ]);

        $response->assertCreated()->assertJsonPath('data.corporate_name', 'Distribuidora Central Ltda');
        $this->assertDatabaseHas('suppliers', ['corporate_name' => 'Distribuidora Central Ltda']);
    }

    public function test_seller_cannot_create_supplier(): void
    {
        $seller = User::factory()->create();

        $response = $this->actingAs($seller)->postJson('/api/suppliers', [
            'corporate_name' => 'Distribuidora Central Ltda',
            'is_company' => true,
        ]);

        $response->assertStatus(403);
    }

    public function test_create_requires_corporate_name(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/suppliers', ['is_company' => true]);

        $response->assertStatus(422)->assertJsonValidationErrors(['corporate_name']);
    }

    public function test_create_rejects_invalid_email(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/suppliers', [
            'corporate_name' => 'Distribuidora Central Ltda',
            'is_company' => true,
            'email' => 'nao-e-email',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_admin_can_update_supplier(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($admin)->putJson("/api/suppliers/{$supplier->id}", [
            'corporate_name' => 'Atualizada Ltda',
            'is_company' => true,
        ]);

        $response->assertOk()->assertJsonPath('data.corporate_name', 'Atualizada Ltda');
    }

    public function test_admin_can_delete_supplier(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/suppliers/{$supplier->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    }
}
