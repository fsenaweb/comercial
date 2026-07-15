<?php

namespace Tests\Feature\AccountsReceivable;

use App\Models\Customer;
use App\Models\ProductVariation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateAccountDebitTest extends TestCase
{
    use RefreshDatabase;

    private function createDebit(User $user, float $salePrice = 10, int $quantity = 3): array
    {
        $customer = Customer::factory()->create();
        $variation = ProductVariation::factory()->create(['sale_price' => $salePrice, 'current_quantity' => 20]);

        $response = $this->actingAs($user)->postJson('/api/accounts-receivable/debits', [
            'customer_id' => $customer->id,
            'description' => 'Compra original',
            'items' => [['product_variation_id' => $variation->id, 'quantity' => $quantity]],
        ]);

        $entryId = $response->json('data.id');
        $itemId = $response->json('data.items.0.id');

        return compact('customer', 'variation', 'entryId', 'itemId');
    }

    public function test_admin_can_revise_item_price_without_touching_stock(): void
    {
        $admin = User::factory()->admin()->create();
        ['variation' => $variation, 'entryId' => $entryId, 'itemId' => $itemId] = $this->createDebit($admin, 10, 3);

        $response = $this->actingAs($admin)->putJson("/api/accounts-receivable/debits/{$entryId}", [
            'items' => [['id' => $itemId, 'unit_price' => 8]],
        ]);

        $response->assertOk()->assertJsonPath('data.amount', '24.00');
        $this->assertEquals(17, $variation->fresh()->current_quantity);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_can_apply_item_discount_on_revision(): void
    {
        $admin = User::factory()->admin()->create();
        ['entryId' => $entryId, 'itemId' => $itemId] = $this->createDebit($admin, 10, 2);

        $response = $this->actingAs($admin)->putJson("/api/accounts-receivable/debits/{$entryId}", [
            'items' => [['id' => $itemId, 'discount_type' => 'fixed', 'discount_value' => 5]],
        ]);

        // gross 20.00 - desconto 5.00 = 15.00
        $response->assertOk()->assertJsonPath('data.amount', '15.00');
    }

    public function test_can_apply_overall_discount_on_revision(): void
    {
        $admin = User::factory()->admin()->create();
        ['entryId' => $entryId, 'itemId' => $itemId] = $this->createDebit($admin, 10, 2);

        $response = $this->actingAs($admin)->putJson("/api/accounts-receivable/debits/{$entryId}", [
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'items' => [['id' => $itemId]],
        ]);

        // subtotal 20.00, 10% => 18.00
        $response->assertOk()->assertJsonPath('data.amount', '18.00');
    }

    public function test_rejects_revision_that_would_leave_balance_negative(): void
    {
        $admin = User::factory()->admin()->create();
        ['entryId' => $entryId, 'itemId' => $itemId, 'customer' => $customer] = $this->createDebit($admin, 10, 3);
        // saldo atual: 30.00

        \App\Models\CashRegister::factory()->open()->create();
        $paymentMethod = \App\Models\PaymentMethod::factory()->create();
        $account = \App\Models\AccountsReceivable::where('customer_id', $customer->id)->firstOrFail();
        $this->actingAs($admin)->postJson("/api/accounts-receivable/{$account->id}/payments", [
            'amount' => 25, 'payment_method_id' => $paymentMethod->id,
        ])->assertCreated();
        // saldo agora: 5.00

        $response = $this->actingAs($admin)->putJson("/api/accounts-receivable/debits/{$entryId}", [
            'items' => [['id' => $itemId, 'unit_price' => 1]],
        ]);
        // reduziria a compra pra 3.00, menor que os 25.00 já pagos

        $response->assertStatus(422)->assertJsonValidationErrors(['amount']);
    }

    public function test_seller_cannot_revise_debit(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = User::factory()->create();
        ['entryId' => $entryId, 'itemId' => $itemId] = $this->createDebit($admin, 10, 2);

        $response = $this->actingAs($seller)->putJson("/api/accounts-receivable/debits/{$entryId}", [
            'items' => [['id' => $itemId, 'unit_price' => 5]],
        ]);

        $response->assertStatus(403);
    }
}
