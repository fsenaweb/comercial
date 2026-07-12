<?php

namespace Tests\Feature\CashRegister;

use App\Enums\CashOperationOrigin;
use App\Enums\CashOperationType;
use App\Models\CashOperation;
use App\Models\CashRegister;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashOperationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_register_cash_withdrawal(): void
    {
        $admin = User::factory()->admin()->create();
        $cashRegister = CashRegister::factory()->open()->create();
        $paymentMethod = PaymentMethod::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/cash-registers/operations', [
            'origin' => 'cash_withdrawal',
            'amount' => 30,
            'payment_method_id' => $paymentMethod->id,
            'notes' => 'Sangria para depósito',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'out')
            ->assertJsonPath('data.origin', 'cash_withdrawal')
            ->assertJsonPath('data.amount', '30.00');

        $this->assertDatabaseHas('cash_operations', [
            'cash_register_id' => $cashRegister->id,
            'type' => 'out',
            'origin' => 'cash_withdrawal',
            'user_id' => $admin->id,
        ]);
    }

    public function test_cashier_can_register_cash_reinforcement(): void
    {
        $cashier = User::factory()->cashier()->create();
        CashRegister::factory()->open()->create();

        $response = $this->actingAs($cashier)->postJson('/api/cash-registers/operations', [
            'origin' => 'cash_reinforcement',
            'amount' => 40,
        ]);

        $response->assertCreated()->assertJsonPath('data.type', 'in');
    }

    public function test_cannot_register_operation_without_open_cash_register(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/cash-registers/operations', [
            'origin' => 'cash_reinforcement',
            'amount' => 40,
        ]);

        $response->assertStatus(422);
    }

    public function test_seller_cannot_register_operation(): void
    {
        $seller = User::factory()->create();
        CashRegister::factory()->open()->create();

        $response = $this->actingAs($seller)->postJson('/api/cash-registers/operations', [
            'origin' => 'cash_reinforcement',
            'amount' => 40,
        ]);

        $response->assertStatus(403);
    }

    public function test_amount_and_origin_are_validated(): void
    {
        $admin = User::factory()->admin()->create();
        CashRegister::factory()->open()->create();

        $response = $this->actingAs($admin)->postJson('/api/cash-registers/operations', [
            'origin' => 'sale',
            'amount' => 0,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['origin', 'amount']);
    }

    public function test_expected_amount_reflects_operations(): void
    {
        $admin = User::factory()->admin()->create();
        $cashRegister = CashRegister::factory()->open()->create(['opening_amount' => 100]);

        $this->actingAs($admin)->postJson('/api/cash-registers/operations', [
            'origin' => 'cash_reinforcement',
            'amount' => 50,
        ]);
        $this->actingAs($admin)->postJson('/api/cash-registers/operations', [
            'origin' => 'cash_withdrawal',
            'amount' => 20,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/cash-registers/current');

        $response->assertOk()->assertJsonPath('data.expected_amount', '130.00');
    }

    public function test_admin_can_remove_operation_from_open_cash_register(): void
    {
        $admin = User::factory()->admin()->create();
        $cashRegister = CashRegister::factory()->open()->create();
        $operation = CashOperation::factory()->create([
            'cash_register_id' => $cashRegister->id,
            'type' => CashOperationType::In,
            'origin' => CashOperationOrigin::CashReinforcement,
        ]);

        $response = $this->actingAs($admin)->deleteJson("/api/cash-registers/operations/{$operation->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('cash_operations', ['id' => $operation->id]);
    }

    public function test_cannot_remove_operation_from_closed_cash_register(): void
    {
        $admin = User::factory()->admin()->create();
        $cashRegister = CashRegister::factory()->closed()->create();
        $operation = CashOperation::factory()->create([
            'cash_register_id' => $cashRegister->id,
            'type' => CashOperationType::In,
            'origin' => CashOperationOrigin::CashReinforcement,
        ]);

        $response = $this->actingAs($admin)->deleteJson("/api/cash-registers/operations/{$operation->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('cash_operations', ['id' => $operation->id]);
    }
}
