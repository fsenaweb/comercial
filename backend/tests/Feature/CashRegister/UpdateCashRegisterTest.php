<?php

namespace Tests\Feature\CashRegister;

use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateCashRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_open_cash_register(): void
    {
        $admin = User::factory()->admin()->create();
        $cashRegister = CashRegister::factory()->open()->create(['opening_amount' => 100]);

        $response = $this->actingAs($admin)->putJson("/api/cash-registers/{$cashRegister->id}", [
            'opening_amount' => 150,
            'notes' => 'Ajuste de fundo de troco',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.opening_amount', '150.00')
            ->assertJsonPath('data.notes', 'Ajuste de fundo de troco');

        $this->assertDatabaseHas('cash_registers', [
            'id' => $cashRegister->id,
            'opening_amount' => 150,
        ]);
    }

    public function test_seller_cannot_update_cash_register(): void
    {
        $seller = User::factory()->create();
        $cashRegister = CashRegister::factory()->open()->create();

        $response = $this->actingAs($seller)->putJson("/api/cash-registers/{$cashRegister->id}", [
            'opening_amount' => 150,
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_update_a_closed_cash_register(): void
    {
        $admin = User::factory()->admin()->create();
        $cashRegister = CashRegister::factory()->closed()->create();

        $response = $this->actingAs($admin)->putJson("/api/cash-registers/{$cashRegister->id}", [
            'opening_amount' => 150,
        ]);

        $response->assertStatus(422);
    }
}
