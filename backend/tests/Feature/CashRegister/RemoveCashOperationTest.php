<?php

namespace Tests\Feature\CashRegister;

use App\Models\CashOperation;
use App\Models\CashRegister;
use App\Models\PaymentMethod;
use App\Models\ProductVariation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemoveCashOperationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_remove_a_manual_operation(): void
    {
        $cashRegister = CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        $operation = CashOperation::factory()->create([
            'cash_register_id' => $cashRegister->id,
            'origin' => 'cash_withdrawal',
        ]);

        $response = $this->actingAs($admin)->deleteJson("/api/cash-registers/operations/{$operation->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('cash_operations', ['id' => $operation->id]);
    }

    public function test_sale_origin_operation_cannot_be_removed(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 20]);
        $saleId = $this->actingAs($admin)->postJson('/api/sales', [
            'payment_method_id' => $paymentMethod->id,
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 1]],
        ])->json('data.id');
        $operation = CashOperation::where('reference_id', $saleId)->where('origin', 'sale')->firstOrFail();

        $response = $this->actingAs($admin)->deleteJson("/api/cash-registers/operations/{$operation->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('cash_operations', ['id' => $operation->id]);
    }

    public function test_cancellation_reversal_operation_cannot_be_removed(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 20]);
        $saleId = $this->actingAs($admin)->postJson('/api/sales', [
            'payment_method_id' => $paymentMethod->id,
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 1]],
        ])->json('data.id');
        $this->actingAs($admin)->postJson("/api/sales/{$saleId}/cancel", ['reason' => 'Motivo']);
        $operation = CashOperation::where('reference_id', $saleId)->where('origin', 'adjustment')->firstOrFail();

        $response = $this->actingAs($admin)->deleteJson("/api/cash-registers/operations/{$operation->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('cash_operations', ['id' => $operation->id]);
    }
}
