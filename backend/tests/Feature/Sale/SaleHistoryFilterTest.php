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
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 10]],
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

    public function test_without_date_filter_shows_only_todays_sales(): void
    {
        $seller = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $today = $this->makeSale($seller);
        $yesterday = $this->makeSale($seller);
        $yesterday->forceFill(['created_at' => now()->subDay()])->save();

        $response = $this->actingAs($admin)->getJson('/api/sales');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $today->id);
    }

    public function test_explicit_date_filter_overrides_the_default_today_filter(): void
    {
        $seller = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $today = $this->makeSale($seller);
        $lastWeek = $this->makeSale($seller);
        $lastWeek->forceFill(['created_at' => now()->subDays(7)])->save();

        $response = $this->actingAs($admin)->getJson('/api/sales?date_from='.now()->subDays(10)->toDateString());

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_default_today_filter_does_not_apply_to_quotes(): void
    {
        $seller = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $quote = $this->makeSale($seller, ['status' => 'pending']);
        $quote->forceFill(['created_at' => now()->subDays(30), 'cash_register_id' => null])->save();

        $response = $this->actingAs($admin)->getJson('/api/sales?is_quote=1');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_filter_summary_reflects_the_whole_filtered_set_not_just_the_current_page(): void
    {
        $seller = User::factory()->create();
        $admin = User::factory()->admin()->create();
        // 3 vendas de R$10 hoje (uma delas cancelada, não deve entrar na
        // soma) + 1 venda antiga (fora do filtro padrão de hoje).
        $this->makeSale($seller);
        $this->makeSale($seller);
        $canceled = $this->makeSale($seller);
        $canceled->forceFill(['status' => 'canceled'])->save();
        $old = $this->makeSale($seller);
        $old->forceFill(['created_at' => now()->subDays(10)])->save();

        $response = $this->actingAs($admin)->getJson('/api/sales');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('filter_summary.total_amount', 20)
            ->assertJsonPath('filter_summary.average_ticket', 10);
    }
}
