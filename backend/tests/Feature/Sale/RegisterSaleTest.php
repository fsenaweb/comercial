<?php

namespace Tests\Feature\Sale;

use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\ProductVariation;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterSaleTest extends TestCase
{
    use RefreshDatabase;

    private function baseData(array $overrides = []): array
    {
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 20]);

        return array_merge([
            'payment_method_id' => $paymentMethod->id,
            'items' => [
                ['product_variation_id' => $variation->id, 'quantity' => 2],
            ],
        ], $overrides);
    }

    public function test_admin_can_register_a_sale(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/sales', $this->baseData());

        $response->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.total', '20.00');

        $this->assertDatabaseCount('sale_items', 1);
        $saleId = $response->json('data.id');
        $this->assertDatabaseHas('cash_operations', ['origin' => 'sale', 'amount' => 20, 'reference_id' => $saleId]);
    }

    public function test_cashier_can_register_a_sale(): void
    {
        CashRegister::factory()->open()->create();
        $cashier = User::factory()->cashier()->create();

        $response = $this->actingAs($cashier)->postJson('/api/sales', $this->baseData());

        $response->assertCreated();
    }

    public function test_seller_can_register_a_sale(): void
    {
        CashRegister::factory()->open()->create();
        $seller = User::factory()->create();

        $response = $this->actingAs($seller)->postJson('/api/sales', $this->baseData());

        $response->assertCreated();
    }

    public function test_guest_cannot_register_a_sale(): void
    {
        CashRegister::factory()->open()->create();

        $this->postJson('/api/sales', $this->baseData())->assertStatus(401);
    }

    public function test_sale_requires_open_cash_register(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/sales', $this->baseData());

        $response->assertStatus(422)->assertJsonValidationErrors(['cash_register']);
    }

    public function test_sale_rejects_insufficient_stock(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 1]);

        $response = $this->actingAs($admin)->postJson('/api/sales', [
            'payment_method_id' => $paymentMethod->id,
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 5]],
        ]);

        $response->assertStatus(422);

        $this->assertEquals(1, $variation->fresh()->current_quantity);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseMissing('stock_movements', [
            'product_variation_id' => $variation->id,
            'type' => 'sale',
        ]);
    }

    public function test_sale_with_exact_available_stock_succeeds(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 5]);

        $response = $this->actingAs($admin)->postJson('/api/sales', [
            'payment_method_id' => $paymentMethod->id,
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 5]],
        ]);

        $response->assertCreated();
        $this->assertEquals(0, $variation->fresh()->current_quantity);
    }

    public function test_wholesale_price_requires_explicit_apply_wholesale_flag(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $variation = ProductVariation::factory()->create([
            'sale_price' => 10,
            'wholesale_min_qty' => 10,
            'wholesale_price' => 8,
            'current_quantity' => 50,
        ]);

        // Quantidade bate o mínimo, mas sem apply_wholesale o preço normal continua valendo
        // — o operador decide se aplica o atacado (checkbox no PDV), não é automático.
        $notApplied = $this->actingAs($admin)->postJson('/api/sales', [
            'payment_method_id' => $paymentMethod->id,
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 10]],
        ]);
        $notApplied->assertCreated()->assertJsonPath('data.items.0.unit_price', '10.00')
            ->assertJsonPath('data.items.0.is_wholesale', false);

        $belowMinimum = $this->actingAs($admin)->postJson('/api/sales', [
            'payment_method_id' => $paymentMethod->id,
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 9, 'apply_wholesale' => true]],
        ]);
        $belowMinimum->assertCreated()->assertJsonPath('data.items.0.unit_price', '10.00')
            ->assertJsonPath('data.items.0.is_wholesale', false);

        $applied = $this->actingAs($admin)->postJson('/api/sales', [
            'payment_method_id' => $paymentMethod->id,
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 10, 'apply_wholesale' => true]],
        ]);
        $applied->assertCreated()->assertJsonPath('data.items.0.unit_price', '8.00')
            ->assertJsonPath('data.items.0.is_wholesale', true);
    }

    public function test_sale_requires_at_least_one_item(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);

        $response = $this->actingAs($admin)->postJson('/api/sales', [
            'payment_method_id' => $paymentMethod->id,
            'items' => [],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['items']);
    }

    public function test_sale_item_requires_valid_product_variation(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);

        $response = $this->actingAs($admin)->postJson('/api/sales', [
            'payment_method_id' => $paymentMethod->id,
            'items' => [['product_variation_id' => 999999, 'quantity' => 1]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['items.0.product_variation_id']);
    }

    public function test_negative_or_zero_quantity_is_rejected(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $variation = ProductVariation::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/sales', [
            'payment_method_id' => $paymentMethod->id,
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 0]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['items.0.quantity']);
    }

    public function test_seller_is_required_when_store_setting_enabled(): void
    {
        CashRegister::factory()->open()->create();
        StoreSetting::current()->update(['require_seller_on_sale' => true]);
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/sales', $this->baseData());

        $response->assertStatus(422)->assertJsonValidationErrors(['seller_id']);
    }

    public function test_seller_defaults_to_authenticated_user_when_not_required(): void
    {
        CashRegister::factory()->open()->create();
        StoreSetting::current()->update(['require_seller_on_sale' => false]);
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/sales', $this->baseData());

        $response->assertCreated();
        $this->assertDatabaseHas('sales', ['seller_id' => $admin->id]);
    }

    public function test_customer_is_optional(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/sales', $this->baseData());

        $response->assertCreated();
        $this->assertDatabaseHas('sales', ['customer_id' => null]);
    }

    public function test_customer_can_be_linked(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/sales', $this->baseData(['customer_id' => $customer->id]));

        $response->assertCreated();
        $this->assertDatabaseHas('sales', ['customer_id' => $customer->id]);
    }

    public function test_payment_method_must_be_active_on_pos(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => false]);
        $variation = ProductVariation::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/sales', [
            'payment_method_id' => $paymentMethod->id,
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 1]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['payment_method_id']);
    }

    public function test_sale_number_is_generated_and_unique(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();

        $first = $this->actingAs($admin)->postJson('/api/sales', $this->baseData());
        $second = $this->actingAs($admin)->postJson('/api/sales', $this->baseData());

        $firstNumber = $first->json('data.number');
        $secondNumber = $second->json('data.number');

        $this->assertMatchesRegularExpression('/^V\d{6}$/', $firstNumber);
        $this->assertNotEquals($firstNumber, $secondNumber);
    }

    public function test_item_and_sale_level_discount_are_applied_to_totals(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 20]);

        // 3 unidades a R$10 = R$30, desconto de item R$5 -> R$25, desconto de venda R$2 -> R$23
        $response = $this->actingAs($admin)->postJson('/api/sales', [
            'payment_method_id' => $paymentMethod->id,
            'discount_type' => 'fixed',
            'discount_value' => 2,
            'items' => [
                ['product_variation_id' => $variation->id, 'quantity' => 3, 'discount_type' => 'fixed', 'discount_value' => 5],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.subtotal', '25.00')
            ->assertJsonPath('data.discount', '2.00')
            ->assertJsonPath('data.total', '23.00');
    }

    public function test_item_and_sale_level_discount_support_percentage(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $variation = ProductVariation::factory()->create(['sale_price' => 12.50, 'current_quantity' => 20]);

        // 3 unidades a R$12,50 = R$37,50, desconto de item 10% -> R$33,75
        $response = $this->actingAs($admin)->postJson('/api/sales', [
            'payment_method_id' => $paymentMethod->id,
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'items' => [
                ['product_variation_id' => $variation->id, 'quantity' => 3, 'discount_type' => 'percentage', 'discount_value' => 10],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.subtotal', '33.75')
            ->assertJsonPath('data.discount', '3.37')
            ->assertJsonPath('data.total', '30.38');
    }

    public function test_percentage_discount_above_100_is_rejected(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/sales', $this->baseData([
            'discount_type' => 'percentage',
            'discount_value' => 150,
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors(['discount_value']);
    }

    public function test_index_lists_sales(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->postJson('/api/sales', $this->baseData());

        $response = $this->actingAs($admin)->getJson('/api/sales');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_show_returns_sale_with_items(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        $saleId = $this->actingAs($admin)->postJson('/api/sales', $this->baseData())->json('data.id');

        $response = $this->actingAs($admin)->getJson("/api/sales/{$saleId}");

        $response->assertOk()->assertJsonCount(1, 'data.items');
    }
}
