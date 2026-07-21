<?php

namespace Tests\Feature\Sale;

use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\ProductVariation;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterSaleTest extends TestCase
{
    use RefreshDatabase;

    private function baseData(array $overrides = []): array
    {
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 20]);
        $total = $overrides['total'] ?? 20;
        unset($overrides['total']);

        return array_merge([
            'payments' => [
                ['payment_method_id' => $paymentMethod->id, 'amount' => $total],
            ],
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
        $this->assertDatabaseCount('sale_payments', 1);
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
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 50]],
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
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 50]],
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
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 100]],
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 10]],
        ]);
        $notApplied->assertCreated()->assertJsonPath('data.items.0.unit_price', '10.00')
            ->assertJsonPath('data.items.0.is_wholesale', false);

        $belowMinimum = $this->actingAs($admin)->postJson('/api/sales', [
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 90]],
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 9, 'apply_wholesale' => true]],
        ]);
        $belowMinimum->assertCreated()->assertJsonPath('data.items.0.unit_price', '10.00')
            ->assertJsonPath('data.items.0.is_wholesale', false);

        $applied = $this->actingAs($admin)->postJson('/api/sales', [
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 80]],
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
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 1]],
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
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 1]],
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
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 1]],
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
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 1]],
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 1]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['payments.0.payment_method_id']);
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
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 23]],
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

        // 3 unidades a R$12,50 = R$37,50, desconto de item 10% -> R$33,75.
        // Desconto de venda 10% sobre R$33,75 = R$3,375, truncado (nunca pra
        // cima, a favor do comerciante — ver ResolvesDiscounts) pra R$3,37 ->
        // total R$30,38.
        $response = $this->actingAs($admin)->postJson('/api/sales', [
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 30.38]],
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

    public function test_percentage_discount_on_half_cent_boundary_truncates_in_favor_of_merchant(): void
    {
        // Achado real (2026-07-19): 10 unidades a R$1,29 = R$12,90, desconto
        // de venda 15% = R$1,935 — a fração de centavo precisa ficar sempre a
        // favor do comerciante (decisão do usuário), então o desconto trunca
        // pra R$1,93 (nunca arredonda pra R$1,94), total R$10,97. Backend
        // (bcdiv, trunca por padrão) e PDV (cartMath.ts, Math.floor) truncam
        // do mesmo jeito de propósito — divergirem foi o bug original: o PDV
        // chegou a arredondar diferente do backend e a venda era rejeitada
        // com "a soma das formas de pagamento precisa ser igual ao total da
        // venda". Ver ResolvesDiscounts::resolveDiscountAmount.
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $variation = ProductVariation::factory()->create(['sale_price' => 1.29, 'current_quantity' => 20]);

        $response = $this->actingAs($admin)->postJson('/api/sales', [
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 10.97]],
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'items' => [
                ['product_variation_id' => $variation->id, 'quantity' => 10],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.subtotal', '12.90')
            ->assertJsonPath('data.discount', '1.93')
            ->assertJsonPath('data.total', '10.97');
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

    public function test_sale_accepts_multiple_payment_methods_summing_to_total(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        $pix = PaymentMethod::factory()->create(['name' => 'Pix', 'active_on_pos' => true]);
        $cash = PaymentMethod::factory()->create(['name' => 'Dinheiro', 'active_on_pos' => true]);
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 20]);

        $response = $this->actingAs($admin)->postJson('/api/sales', [
            'payments' => [
                ['payment_method_id' => $pix->id, 'amount' => 12.50],
                ['payment_method_id' => $cash->id, 'amount' => 7.50],
            ],
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 2]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.total', '20.00')
            ->assertJsonCount(2, 'data.payments');

        $saleId = $response->json('data.id');
        $this->assertDatabaseCount('sale_payments', 2);
        $this->assertDatabaseHas('sale_payments', ['sale_id' => $saleId, 'payment_method_id' => $pix->id, 'amount' => 12.50]);
        $this->assertDatabaseHas('sale_payments', ['sale_id' => $saleId, 'payment_method_id' => $cash->id, 'amount' => 7.50]);
        $this->assertDatabaseHas('cash_operations', ['reference_id' => $saleId, 'payment_method_id' => $pix->id, 'amount' => 12.50]);
        $this->assertDatabaseHas('cash_operations', ['reference_id' => $saleId, 'payment_method_id' => $cash->id, 'amount' => 7.50]);
    }

    public function test_sale_rejects_payments_sum_that_does_not_match_total(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        $pix = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $cash = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 20]);

        $response = $this->actingAs($admin)->postJson('/api/sales', [
            'payments' => [
                ['payment_method_id' => $pix->id, 'amount' => 12],
                ['payment_method_id' => $cash->id, 'amount' => 7],
            ],
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 2]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['payments']);
        $this->assertDatabaseCount('sale_payments', 0);
        $this->assertDatabaseCount('sale_items', 0);
    }

    public function test_sale_requires_at_least_one_payment(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 20]);

        $response = $this->actingAs($admin)->postJson('/api/sales', [
            'payments' => [],
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 2]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['payments']);
    }

    public function test_sale_discount_above_20_percent_requires_admin_password(): void
    {
        CashRegister::factory()->open()->create();
        User::factory()->admin()->create();
        $seller = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 20]);

        $response = $this->actingAs($seller)->postJson('/api/sales', [
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 15]],
            'discount_type' => 'percentage',
            'discount_value' => 25,
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 2]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['admin_password']);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_sale_item_discount_above_20_percent_requires_admin_password(): void
    {
        CashRegister::factory()->open()->create();
        User::factory()->admin()->create();
        $seller = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 20]);

        $response = $this->actingAs($seller)->postJson('/api/sales', [
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 15]],
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 2, 'discount_type' => 'percentage', 'discount_value' => 25]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['admin_password']);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_sale_discount_above_20_percent_with_wrong_admin_password_is_rejected(): void
    {
        CashRegister::factory()->open()->create();
        User::factory()->admin()->create();
        $seller = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 20]);

        $response = $this->actingAs($seller)->postJson('/api/sales', [
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 15]],
            'discount_type' => 'percentage',
            'discount_value' => 25,
            'admin_password' => 'senha-errada',
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 2]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['admin_password']);
    }

    public function test_sale_discount_above_20_percent_with_valid_admin_password_succeeds(): void
    {
        CashRegister::factory()->open()->create();
        User::factory()->admin()->create(['password' => Hash::make('senha-do-dono')]);
        $seller = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 20]);

        $response = $this->actingAs($seller)->postJson('/api/sales', [
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 15]],
            'discount_type' => 'percentage',
            'discount_value' => 25,
            'admin_password' => 'senha-do-dono',
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 2]],
        ]);

        $response->assertCreated()->assertJsonPath('data.total', '15.00');
    }

    public function test_sale_discount_at_exactly_20_percent_does_not_require_admin_password(): void
    {
        CashRegister::factory()->open()->create();
        $seller = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 20]);

        $response = $this->actingAs($seller)->postJson('/api/sales', [
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 16]],
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 2]],
        ]);

        $response->assertCreated()->assertJsonPath('data.total', '16.00');
    }
}
