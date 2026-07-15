<?php

namespace Tests\Feature\AccountsReceivable;

use App\Models\Customer;
use App\Models\ProductVariation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterAccountDebitTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_register_an_itemized_debit_opening_the_account_and_decrementing_stock(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create();
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 20]);

        $response = $this->actingAs($cashier)->postJson('/api/accounts-receivable/debits', [
            'customer_id' => $customer->id,
            'description' => 'Compra de peças 05/07',
            'items' => [
                ['product_variation_id' => $variation->id, 'quantity' => 3],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'purchase')
            ->assertJsonPath('data.amount', '30.00')
            ->assertJsonCount(1, 'data.items');

        $this->assertDatabaseHas('accounts_receivable', ['customer_id' => $customer->id]);
        $this->assertDatabaseHas('account_entries', ['type' => 'purchase', 'amount' => '30.00']);
        $this->assertDatabaseHas('account_entry_items', ['product_variation_id' => $variation->id, 'quantity' => 3, 'unit_price' => '10.00']);
        $this->assertEquals(17, $variation->fresh()->current_quantity);
        $this->assertDatabaseHas('stock_movements', ['product_variation_id' => $variation->id, 'type' => 'out', 'quantity' => 3]);
    }

    public function test_unit_price_can_be_overridden_and_item_discount_applied(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create();
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 20]);

        $response = $this->actingAs($cashier)->postJson('/api/accounts-receivable/debits', [
            'customer_id' => $customer->id,
            'description' => 'Compra com desconto',
            'items' => [
                ['product_variation_id' => $variation->id, 'quantity' => 2, 'unit_price' => 8, 'discount_type' => 'fixed', 'discount_value' => 1],
            ],
        ]);

        // gross = 16.00, discount 1.00 => item total 15.00
        $response->assertCreated()->assertJsonPath('data.amount', '15.00');
    }

    public function test_overall_discount_applies_on_top_of_items(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create();
        $variation = ProductVariation::factory()->create(['sale_price' => 50, 'current_quantity' => 20]);

        $response = $this->actingAs($cashier)->postJson('/api/accounts-receivable/debits', [
            'customer_id' => $customer->id,
            'description' => 'Compra com desconto geral',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'items' => [
                ['product_variation_id' => $variation->id, 'quantity' => 2],
            ],
        ]);

        // subtotal 100.00, 10% desconto geral => 90.00
        $response->assertCreated()->assertJsonPath('data.amount', '90.00');
    }

    public function test_rejects_when_stock_is_insufficient(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create();
        $variation = ProductVariation::factory()->create(['current_quantity' => 1]);

        $response = $this->actingAs($cashier)->postJson('/api/accounts-receivable/debits', [
            'customer_id' => $customer->id,
            'description' => 'Compra grande demais',
            'items' => [
                ['product_variation_id' => $variation->id, 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertEquals(1, $variation->fresh()->current_quantity);
    }

    public function test_multiple_debits_accumulate_on_the_same_account(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create();
        $variation = ProductVariation::factory()->create(['sale_price' => 25, 'current_quantity' => 20]);

        $this->actingAs($cashier)->postJson('/api/accounts-receivable/debits', [
            'customer_id' => $customer->id, 'description' => 'Compra 1',
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 2]],
        ])->assertCreated();
        $this->actingAs($cashier)->postJson('/api/accounts-receivable/debits', [
            'customer_id' => $customer->id, 'description' => 'Compra 2',
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->assertDatabaseCount('accounts_receivable', 1);
        $account = \App\Models\AccountsReceivable::first();
        $this->assertEquals('75.00', $account->balance());
    }

    public function test_seller_cannot_register_debit(): void
    {
        $seller = User::factory()->create();
        $customer = Customer::factory()->create();
        $variation = ProductVariation::factory()->create();

        $response = $this->actingAs($seller)->postJson('/api/accounts-receivable/debits', [
            'customer_id' => $customer->id, 'description' => 'Compra',
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 1]],
        ]);

        $response->assertStatus(403);
    }

    public function test_items_and_description_are_validated(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($cashier)->postJson('/api/accounts-receivable/debits', [
            'customer_id' => $customer->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['description', 'items']);
    }
}
