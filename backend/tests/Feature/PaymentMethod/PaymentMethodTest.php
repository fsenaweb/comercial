<?php

namespace Tests\Feature\PaymentMethod;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_payment_methods(): void
    {
        $this->getJson('/api/payment-methods')->assertStatus(401);
    }

    public function test_authenticated_user_can_list_payment_methods(): void
    {
        PaymentMethod::factory()->count(2)->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/payment-methods');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_admin_can_create_payment_method(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/payment-methods', [
            'name' => 'Vale Alimentação',
            'active_on_pos' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Vale Alimentação')
            ->assertJsonPath('data.active_on_pos', true);
        $this->assertDatabaseHas('payment_methods', ['name' => 'Vale Alimentação']);
    }

    public function test_seller_cannot_create_payment_method(): void
    {
        $seller = User::factory()->create();

        $response = $this->actingAs($seller)->postJson('/api/payment-methods', ['name' => 'Vale Alimentação']);

        $response->assertStatus(403);
    }

    public function test_create_requires_name(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/payment-methods', []);

        $response->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    public function test_admin_can_update_payment_method(): void
    {
        $admin = User::factory()->admin()->create();
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);

        $response = $this->actingAs($admin)->putJson("/api/payment-methods/{$paymentMethod->id}", [
            'name' => 'Atualizada',
            'active_on_pos' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Atualizada')
            ->assertJsonPath('data.active_on_pos', false);
    }

    public function test_seller_cannot_update_payment_method(): void
    {
        $seller = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create();

        $response = $this->actingAs($seller)->putJson("/api/payment-methods/{$paymentMethod->id}", ['name' => 'Atualizada']);

        $response->assertStatus(403);
    }

    public function test_admin_can_delete_payment_method(): void
    {
        $admin = User::factory()->admin()->create();
        $paymentMethod = PaymentMethod::factory()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/payment-methods/{$paymentMethod->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('payment_methods', ['id' => $paymentMethod->id]);
    }

    public function test_seller_cannot_delete_payment_method(): void
    {
        $seller = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create();

        $response = $this->actingAs($seller)->deleteJson("/api/payment-methods/{$paymentMethod->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('payment_methods', ['id' => $paymentMethod->id]);
    }
}
