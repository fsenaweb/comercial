<?php

namespace Tests\Feature\Sale;

use App\Models\CashRegister;
use App\Models\PaymentMethod;
use App\Models\ProductVariation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelSaleTest extends TestCase
{
    use RefreshDatabase;

    private function makeSale(User $admin): array
    {
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 20]);

        $response = $this->actingAs($admin)->postJson('/api/sales', [
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 30]],
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 3]],
        ]);

        return [$response->json('data.id'), $variation];
    }

    public function test_admin_can_cancel_a_sale_and_stock_is_reverted(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        [$saleId, $variation] = $this->makeSale($admin);

        $this->assertEquals(17, $variation->fresh()->current_quantity);

        $response = $this->actingAs($admin)->postJson("/api/sales/{$saleId}/cancel", [
            'reason' => 'Cliente desistiu da compra',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'canceled')
            ->assertJsonPath('data.canceled_reason', 'Cliente desistiu da compra');

        $this->assertEquals(20, $variation->fresh()->current_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_variation_id' => $variation->id,
            'type' => 'in',
            'quantity' => 3,
        ]);
        $this->assertDatabaseHas('cash_operations', [
            'reference_id' => $saleId,
            'type' => 'out',
            'origin' => 'adjustment',
            'amount' => 30,
        ]);
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'status' => 'canceled']);
    }

    public function test_cashier_can_cancel_a_sale(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        [$saleId] = $this->makeSale($admin);
        $cashier = User::factory()->cashier()->create();

        $response = $this->actingAs($cashier)->postJson("/api/sales/{$saleId}/cancel", [
            'reason' => 'Erro de digitação',
        ]);

        $response->assertOk();
    }

    public function test_seller_cannot_cancel_a_sale(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        [$saleId] = $this->makeSale($admin);
        $seller = User::factory()->create();

        $response = $this->actingAs($seller)->postJson("/api/sales/{$saleId}/cancel", [
            'reason' => 'Erro de digitação',
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_cancel_an_already_canceled_sale(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        [$saleId] = $this->makeSale($admin);

        $this->actingAs($admin)->postJson("/api/sales/{$saleId}/cancel", ['reason' => 'Primeiro cancelamento']);

        $response = $this->actingAs($admin)->postJson("/api/sales/{$saleId}/cancel", ['reason' => 'Segundo cancelamento']);

        $response->assertStatus(422);
    }

    public function test_cancel_requires_open_cash_register(): void
    {
        $cashRegister = CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        [$saleId] = $this->makeSale($admin);
        $cashRegister->update(['status' => 'closed']);

        $response = $this->actingAs($admin)->postJson("/api/sales/{$saleId}/cancel", ['reason' => 'Motivo qualquer']);

        $response->assertStatus(422)->assertJsonValidationErrors(['cash_register']);
    }

    public function test_reason_is_required_to_cancel(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        [$saleId] = $this->makeSale($admin);

        $response = $this->actingAs($admin)->postJson("/api/sales/{$saleId}/cancel", []);

        $response->assertStatus(422)->assertJsonValidationErrors(['reason']);
    }

    public function test_guest_cannot_cancel_a_sale(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        [$saleId] = $this->makeSale($admin);
        $this->app['auth']->forgetGuards();

        $this->postJson("/api/sales/{$saleId}/cancel", ['reason' => 'Motivo'])->assertStatus(401);
    }

    public function test_index_can_filter_by_status(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        [$saleId] = $this->makeSale($admin);
        $this->actingAs($admin)->postJson("/api/sales/{$saleId}/cancel", ['reason' => 'Motivo']);
        $this->makeSale($admin);

        $canceled = $this->actingAs($admin)->getJson('/api/sales?status=canceled');
        $completed = $this->actingAs($admin)->getJson('/api/sales?status=completed');

        $canceled->assertOk()->assertJsonCount(1, 'data');
        $completed->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_canceling_a_split_payment_sale_reverts_one_cash_operation_per_payment_leg(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        $pix = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $cash = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 20]);

        $saleId = $this->actingAs($admin)->postJson('/api/sales', [
            'payments' => [
                ['payment_method_id' => $pix->id, 'amount' => 12],
                ['payment_method_id' => $cash->id, 'amount' => 18],
            ],
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 3]],
        ])->json('data.id');

        $response = $this->actingAs($admin)->postJson("/api/sales/{$saleId}/cancel", ['reason' => 'Cliente desistiu']);

        $response->assertOk()->assertJsonPath('data.status', 'canceled');
        $this->assertDatabaseHas('cash_operations', [
            'reference_id' => $saleId, 'type' => 'out', 'origin' => 'adjustment', 'payment_method_id' => $pix->id, 'amount' => 12,
        ]);
        $this->assertDatabaseHas('cash_operations', [
            'reference_id' => $saleId, 'type' => 'out', 'origin' => 'adjustment', 'payment_method_id' => $cash->id, 'amount' => 18,
        ]);
        $this->assertEquals(
            2,
            \App\Models\CashOperation::where('reference_id', $saleId)->where('type', 'out')->count(),
        );
    }
}
