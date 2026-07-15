<?php

namespace Tests\Feature\Sale;

use App\Models\CashRegister;
use App\Models\PaymentMethod;
use App\Models\ProductVariation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelQuoteTest extends TestCase
{
    use RefreshDatabase;

    private function makeQuote(User $user): array
    {
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 20]);

        $response = $this->actingAs($user)->postJson('/api/quotes', [
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 3]],
        ]);

        return [$response->json('data.id'), $variation];
    }

    public function test_admin_can_cancel_a_pending_quote_without_an_open_cash_register(): void
    {
        $admin = User::factory()->admin()->create();
        [$quoteId, $variation] = $this->makeQuote($admin);

        $response = $this->actingAs($admin)->postJson("/api/sales/{$quoteId}/cancel", [
            'reason' => 'Cliente não aprovou o orçamento',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'canceled');
        $this->assertEquals(20, $variation->fresh()->current_quantity);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('cash_operations', 0);
    }

    public function test_cannot_cancel_an_already_converted_quote(): void
    {
        $admin = User::factory()->admin()->create();
        [$quoteId] = $this->makeQuote($admin);
        CashRegister::factory()->open()->create();
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $this->actingAs($admin)->postJson("/api/sales/{$quoteId}/convert", [
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 30]],
        ]);

        $response = $this->actingAs($admin)->postJson("/api/sales/{$quoteId}/cancel", ['reason' => 'Motivo qualquer']);

        $response->assertStatus(422);
    }

    public function test_seller_cannot_cancel_a_quote(): void
    {
        $admin = User::factory()->admin()->create();
        [$quoteId] = $this->makeQuote($admin);
        $seller = User::factory()->create();

        $response = $this->actingAs($seller)->postJson("/api/sales/{$quoteId}/cancel", ['reason' => 'Motivo qualquer']);

        $response->assertStatus(403);
    }
}
