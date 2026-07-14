<?php

namespace Tests\Feature\Sale;

use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\ProductVariation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteTest extends TestCase
{
    use RefreshDatabase;

    private function baseData(array $overrides = []): array
    {
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 20]);

        return array_merge([
            'items' => [
                ['product_variation_id' => $variation->id, 'quantity' => 2],
            ],
        ], $overrides);
    }

    public function test_quote_can_be_created_without_an_open_cash_register(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/quotes', $this->baseData());

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total', '20.00');

        $this->assertMatchesRegularExpression('/^O\d{6}$/', $response->json('data.number'));
    }

    public function test_quote_does_not_touch_stock_or_cash(): void
    {
        $admin = User::factory()->admin()->create();
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 20]);

        $this->actingAs($admin)->postJson('/api/quotes', [
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 5]],
        ])->assertCreated();

        $this->assertEquals(20, $variation->fresh()->current_quantity);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('cash_operations', 0);
    }

    public function test_quote_ignores_stock_availability(): void
    {
        $admin = User::factory()->admin()->create();
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 1]);

        $response = $this->actingAs($admin)->postJson('/api/quotes', [
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 50]],
        ]);

        $response->assertCreated();
    }

    public function test_seller_can_create_a_quote(): void
    {
        $seller = User::factory()->create();

        $response = $this->actingAs($seller)->postJson('/api/quotes', $this->baseData());

        $response->assertCreated();
    }

    public function test_guest_cannot_create_a_quote(): void
    {
        $this->postJson('/api/quotes', $this->baseData())->assertStatus(401);
    }

    public function test_quote_accepts_optional_expiration_date(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/quotes', $this->baseData([
            'expires_at' => now()->addDays(7)->toDateString(),
        ]));

        $response->assertCreated();
        $this->assertNotNull($response->json('data.expires_at'));
    }

    public function test_quote_rejects_expiration_date_in_the_past(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/quotes', $this->baseData([
            'expires_at' => now()->subDay()->toDateString(),
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors(['expires_at']);
    }

    public function test_quote_can_be_linked_to_a_customer(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/quotes', $this->baseData(['customer_id' => $customer->id]));

        $response->assertCreated();
        $this->assertDatabaseHas('sales', ['customer_id' => $customer->id, 'status' => 'pending']);
    }

    public function test_index_can_filter_quotes_from_real_sales(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->postJson('/api/quotes', $this->baseData());

        $response = $this->actingAs($admin)->getJson('/api/sales?is_quote=1');

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_index_can_exclude_quotes_from_real_sales(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->postJson('/api/quotes', $this->baseData());
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 20]);
        $this->actingAs($admin)->postJson('/api/sales', [
            'payment_method_id' => $paymentMethod->id,
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 1]],
        ]);

        $response = $this->actingAs($admin)->getJson('/api/sales?is_quote=0');

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'completed');
    }
}
