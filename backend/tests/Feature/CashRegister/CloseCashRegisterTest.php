<?php

namespace Tests\Feature\CashRegister;

use App\Enums\CashOperationOrigin;
use App\Enums\CashOperationType;
use App\Models\CashOperation;
use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CloseCashRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_close_cash_register_with_expected_amount(): void
    {
        $admin = User::factory()->admin()->create();
        $cashRegister = CashRegister::factory()->open()->create(['opening_amount' => 100]);

        CashOperation::factory()->create([
            'cash_register_id' => $cashRegister->id,
            'type' => CashOperationType::In,
            'origin' => CashOperationOrigin::CashReinforcement,
            'amount' => 50,
        ]);
        CashOperation::factory()->create([
            'cash_register_id' => $cashRegister->id,
            'type' => CashOperationType::Out,
            'origin' => CashOperationOrigin::CashWithdrawal,
            'amount' => 20,
        ]);

        $response = $this->actingAs($admin)->postJson("/api/cash-registers/{$cashRegister->id}/close", [
            'closing_amount' => 130,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.expected_amount', '130.00')
            ->assertJsonPath('data.closing_amount', '130.00')
            ->assertJsonPath('data.difference_amount', '0.00')
            ->assertJsonPath('data.closed_by_name', $admin->name);

        $this->assertDatabaseHas('cash_registers', [
            'id' => $cashRegister->id,
            'status' => 'closed',
            'closed_by' => $admin->id,
        ]);
    }

    public function test_seller_cannot_close_cash_register(): void
    {
        $seller = User::factory()->create();
        $cashRegister = CashRegister::factory()->open()->create();

        $response = $this->actingAs($seller)->postJson("/api/cash-registers/{$cashRegister->id}/close", [
            'closing_amount' => 100,
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_close_an_already_closed_cash_register(): void
    {
        $admin = User::factory()->admin()->create();
        $cashRegister = CashRegister::factory()->closed()->create();

        $response = $this->actingAs($admin)->postJson("/api/cash-registers/{$cashRegister->id}/close", [
            'closing_amount' => 100,
        ]);

        $response->assertStatus(422);
    }

    public function test_closing_amount_is_required(): void
    {
        $admin = User::factory()->admin()->create();
        $cashRegister = CashRegister::factory()->open()->create();

        $response = $this->actingAs($admin)->postJson("/api/cash-registers/{$cashRegister->id}/close", []);

        $response->assertStatus(422)->assertJsonValidationErrors(['closing_amount']);
    }
}
