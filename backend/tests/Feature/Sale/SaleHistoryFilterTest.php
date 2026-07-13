<?php

namespace Tests\Feature\Sale;

use App\Models\CashRegister;
use App\Models\PaymentMethod;
use App\Models\ProductVariation;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleHistoryFilterTest extends TestCase
{
    use RefreshDatabase;

    private function makeSale(User $seller, array $overrides = []): Sale
    {
        $cashRegister = CashRegister::factory()->open()->create();
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 20]);

        $saleId = $this->actingAs($seller)->postJson('/api/sales', array_merge([
            'payment_method_id' => $paymentMethod->id,
            'seller_id' => $seller->id,
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 1]],
        ], $overrides))->json('data.id');

        $this->actingAs($seller)->postJson("/api/cash-registers/{$cashRegister->id}/close", ['closing_amount' => 0]);

        return Sale::findOrFail($saleId);
    }

    public function test_can_filter_by_seller(): void
    {
        $sellerA = User::factory()->create();
        $sellerB = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $this->makeSale($sellerA);
        $this->makeSale($sellerB);

        $response = $this->actingAs($admin)->getJson("/api/sales?seller_id={$sellerA->id}");

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.seller_id', $sellerA->id);
    }

    public function test_can_filter_by_period(): void
    {
        $seller = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $sale = $this->makeSale($seller);
        $sale->forceFill(['created_at' => now()->subDays(10)])->save();

        $response = $this->actingAs($admin)->getJson('/api/sales?date_from='.now()->subDays(1)->toDateString());

        $response->assertOk()->assertJsonCount(0, 'data');

        $response = $this->actingAs($admin)->getJson('/api/sales?date_to='.now()->subDays(5)->toDateString());

        $response->assertOk()->assertJsonCount(1, 'data');
    }
}
