<?php

namespace Tests\Feature\CashRegister;

use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListCashRegistersTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_authenticated_user_can_list_cash_registers(): void
    {
        $seller = User::factory()->create();
        CashRegister::factory()->open()->create();
        CashRegister::factory()->closed()->create();

        $response = $this->actingAs($seller)->getJson('/api/cash-registers');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_can_filter_by_status(): void
    {
        $admin = User::factory()->admin()->create();
        CashRegister::factory()->open()->create();
        CashRegister::factory()->closed()->create();

        $response = $this->actingAs($admin)->getJson('/api/cash-registers?status=open');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'open');
    }

    public function test_can_search_by_notes(): void
    {
        $admin = User::factory()->admin()->create();
        CashRegister::factory()->open()->create(['notes' => 'Fundo de troco especial']);
        CashRegister::factory()->closed()->create(['notes' => 'Outro caixa']);

        $response = $this->actingAs($admin)->getJson('/api/cash-registers?search=especial');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_guest_cannot_list_cash_registers(): void
    {
        $this->getJson('/api/cash-registers')->assertStatus(401);
    }
}
