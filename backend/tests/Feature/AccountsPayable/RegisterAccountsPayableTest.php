<?php

namespace Tests\Feature\AccountsPayable;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterAccountsPayableTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_register_accounts_payable_with_installments(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/accounts-payable', [
            'supplier_id' => $supplier->id,
            'description' => 'NF-e 1234',
            'total_amount' => 300,
            'installments' => [
                ['number' => 1, 'amount' => 150, 'due_date' => now()->addDays(30)->toDateString()],
                ['number' => 2, 'amount' => 150, 'due_date' => now()->addDays(60)->toDateString()],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.installments_count', 2)
            ->assertJsonCount(2, 'data.installments');

        $this->assertDatabaseHas('accounts_payable', [
            'supplier_id' => $supplier->id,
            'total_amount' => '300.00',
        ]);
        $this->assertDatabaseCount('payable_installments', 2);
    }

    public function test_rejects_when_installments_sum_does_not_match_total(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/accounts-payable', [
            'supplier_id' => $supplier->id,
            'description' => 'NF-e 1234',
            'total_amount' => 300,
            'installments' => [
                ['number' => 1, 'amount' => 100, 'due_date' => now()->addDays(30)->toDateString()],
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['installments']);
    }

    public function test_cashier_cannot_register_accounts_payable(): void
    {
        $cashier = User::factory()->cashier()->create();
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($cashier)->postJson('/api/accounts-payable', [
            'supplier_id' => $supplier->id,
            'description' => 'NF-e 1234',
            'total_amount' => 100,
            'installments' => [
                ['number' => 1, 'amount' => 100, 'due_date' => now()->addDays(30)->toDateString()],
            ],
        ]);

        $response->assertStatus(403);
    }

    public function test_supplier_id_is_required(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/accounts-payable', [
            'description' => 'NF-e 1234',
            'total_amount' => 100,
            'installments' => [
                ['number' => 1, 'amount' => 100, 'due_date' => now()->addDays(30)->toDateString()],
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['supplier_id']);
    }
}
