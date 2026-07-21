<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_customers(): void
    {
        $this->getJson('/api/customers')->assertStatus(401);
    }

    public function test_authenticated_user_can_list_customers(): void
    {
        Customer::factory()->count(2)->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/customers');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_seller_can_create_customer(): void
    {
        $seller = User::factory()->create();

        $response = $this->actingAs($seller)->postJson('/api/customers', [
            'name' => 'João da Silva',
            'mobile_phone' => '11999998888',
            'is_company' => false,
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'João da Silva');
        $this->assertDatabaseHas('customers', ['name' => 'João da Silva']);
    }

    public function test_create_requires_name(): void
    {
        $seller = User::factory()->create();

        $response = $this->actingAs($seller)->postJson('/api/customers', ['is_company' => false]);

        $response->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    public function test_seller_can_create_customer_without_mobile_phone(): void
    {
        $seller = User::factory()->create();

        $response = $this->actingAs($seller)->postJson('/api/customers', [
            'name' => 'Cliente Sem Celular',
            'is_company' => false,
        ]);

        $response->assertCreated()->assertJsonPath('data.mobile_phone', null);
        $this->assertDatabaseHas('customers', ['name' => 'Cliente Sem Celular', 'mobile_phone' => null]);
    }

    public function test_seller_can_update_customer(): void
    {
        $seller = User::factory()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($seller)->putJson("/api/customers/{$customer->id}", [
            'name' => 'Atualizado',
            'mobile_phone' => $customer->mobile_phone,
            'is_company' => false,
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'Atualizado');
    }

    public function test_seller_cannot_delete_customer(): void
    {
        $seller = User::factory()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($seller)->deleteJson("/api/customers/{$customer->id}");

        $response->assertStatus(403);
    }

    public function test_admin_can_delete_customer(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/customers/{$customer->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }
}
