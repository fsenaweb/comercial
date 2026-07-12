<?php

namespace Tests\Feature\CashRegister;

use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenCashRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_cash_register(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/cash-registers/open', [
            'opening_amount' => 100,
            'notes' => 'Fundo de troco',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.opening_amount', '100.00')
            ->assertJsonPath('data.opened_by_name', $admin->name);

        $this->assertDatabaseHas('cash_registers', [
            'opening_amount' => 100,
            'status' => 'open',
            'opened_by' => $admin->id,
        ]);
    }

    public function test_cashier_can_open_cash_register(): void
    {
        $cashier = User::factory()->cashier()->create();

        $response = $this->actingAs($cashier)->postJson('/api/cash-registers/open', [
            'opening_amount' => 50,
        ]);

        $response->assertCreated();
    }

    public function test_seller_cannot_open_cash_register(): void
    {
        $seller = User::factory()->create();

        $response = $this->actingAs($seller)->postJson('/api/cash-registers/open', [
            'opening_amount' => 100,
        ]);

        $response->assertStatus(403);
    }

    public function test_guest_cannot_open_cash_register(): void
    {
        $this->postJson('/api/cash-registers/open', ['opening_amount' => 100])->assertStatus(401);
    }

    public function test_opening_amount_is_required(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/cash-registers/open', []);

        $response->assertStatus(422)->assertJsonValidationErrors(['opening_amount']);
    }

    public function test_cannot_open_a_second_cash_register(): void
    {
        $admin = User::factory()->admin()->create();
        CashRegister::factory()->open()->create();

        $response = $this->actingAs($admin)->postJson('/api/cash-registers/open', [
            'opening_amount' => 100,
        ]);

        $response->assertStatus(409);
    }
}
