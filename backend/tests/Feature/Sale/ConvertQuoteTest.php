<?php

namespace Tests\Feature\Sale;

use App\Models\CashRegister;
use App\Models\PaymentMethod;
use App\Models\ProductVariation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConvertQuoteTest extends TestCase
{
    use RefreshDatabase;

    private function makeQuote(User $user, array $overrides = []): array
    {
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 20]);

        $response = $this->actingAs($user)->postJson('/api/quotes', array_merge([
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 3]],
        ], $overrides));

        return [$response->json('data.id'), $variation];
    }

    public function test_admin_can_convert_a_pending_quote_into_a_real_sale(): void
    {
        $admin = User::factory()->admin()->create();
        [$quoteId, $variation] = $this->makeQuote($admin);
        CashRegister::factory()->open()->create();
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);

        $response = $this->actingAs($admin)->postJson("/api/sales/{$quoteId}/convert", [
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 30]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.total', '30.00');

        $newSaleId = $response->json('data.id');
        $this->assertNotEquals($quoteId, $newSaleId);
        $this->assertMatchesRegularExpression('/^V\d{6}$/', $response->json('data.number'));

        $this->assertEquals(17, $variation->fresh()->current_quantity);
        $this->assertDatabaseHas('cash_operations', ['reference_id' => $newSaleId, 'origin' => 'sale', 'amount' => 30]);
        $this->assertDatabaseHas('sales', [
            'id' => $quoteId,
            'status' => 'converted',
            'converted_to_sale_id' => $newSaleId,
        ]);
    }

    public function test_convert_accepts_multiple_payment_methods(): void
    {
        $admin = User::factory()->admin()->create();
        [$quoteId] = $this->makeQuote($admin);
        CashRegister::factory()->open()->create();
        $pix = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $cash = PaymentMethod::factory()->create(['active_on_pos' => true]);

        $response = $this->actingAs($admin)->postJson("/api/sales/{$quoteId}/convert", [
            'payments' => [
                ['payment_method_id' => $pix->id, 'amount' => 20],
                ['payment_method_id' => $cash->id, 'amount' => 10],
            ],
        ]);

        $response->assertCreated()->assertJsonCount(2, 'data.payments');
    }

    public function test_convert_requires_open_cash_register(): void
    {
        $admin = User::factory()->admin()->create();
        [$quoteId] = $this->makeQuote($admin);
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);

        $response = $this->actingAs($admin)->postJson("/api/sales/{$quoteId}/convert", [
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 30]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['cash_register']);
        $this->assertDatabaseHas('sales', ['id' => $quoteId, 'status' => 'pending']);
    }

    public function test_convert_rejects_insufficient_stock(): void
    {
        $admin = User::factory()->admin()->create();
        [$quoteId, $variation] = $this->makeQuote($admin);
        $variation->update(['current_quantity' => 1]);
        CashRegister::factory()->open()->create();
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);

        $response = $this->actingAs($admin)->postJson("/api/sales/{$quoteId}/convert", [
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 30]],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('sales', ['id' => $quoteId, 'status' => 'pending']);
    }

    public function test_cannot_convert_an_already_converted_quote(): void
    {
        $admin = User::factory()->admin()->create();
        [$quoteId] = $this->makeQuote($admin);
        CashRegister::factory()->open()->create();
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);

        $this->actingAs($admin)->postJson("/api/sales/{$quoteId}/convert", [
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 30]],
        ]);

        $response = $this->actingAs($admin)->postJson("/api/sales/{$quoteId}/convert", [
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 30]],
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_convert_an_expired_quote(): void
    {
        $admin = User::factory()->admin()->create();
        [$quoteId] = $this->makeQuote($admin);
        $sale = \App\Models\Sale::find($quoteId);
        $sale->forceFill(['expires_at' => now()->subDay()])->save();
        CashRegister::factory()->open()->create();
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);

        $response = $this->actingAs($admin)->postJson("/api/sales/{$quoteId}/convert", [
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 30]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['expires_at']);
    }

    public function test_seller_cannot_convert_a_quote(): void
    {
        $admin = User::factory()->admin()->create();
        [$quoteId] = $this->makeQuote($admin);
        CashRegister::factory()->open()->create();
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $seller = User::factory()->create();

        $response = $this->actingAs($seller)->postJson("/api/sales/{$quoteId}/convert", [
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 30]],
        ]);

        $response->assertStatus(403);
    }

    public function test_convert_requires_payment_method(): void
    {
        $admin = User::factory()->admin()->create();
        [$quoteId] = $this->makeQuote($admin);
        CashRegister::factory()->open()->create();

        $response = $this->actingAs($admin)->postJson("/api/sales/{$quoteId}/convert", []);

        $response->assertStatus(422)->assertJsonValidationErrors(['payments']);
    }
}
