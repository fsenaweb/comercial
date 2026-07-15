<?php

namespace Tests\Feature\AccountsPayable;

use App\Models\AccountsPayable;
use App\Models\PayableInstallment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettlePayableInstallmentTest extends TestCase
{
    use RefreshDatabase;

    private function createPayableWithInstallments(int $count = 2): AccountsPayable
    {
        $supplier = Supplier::factory()->create();
        $admin = User::factory()->admin()->create();

        $payable = AccountsPayable::create([
            'supplier_id' => $supplier->id,
            'description' => 'NF-e 1234',
            'total_amount' => 100 * $count,
            'installments_count' => $count,
            'status' => 'open',
            'created_by' => $admin->id,
        ]);

        for ($i = 1; $i <= $count; $i++) {
            PayableInstallment::create([
                'accounts_payable_id' => $payable->id,
                'number' => $i,
                'amount' => 100,
                'due_date' => now()->addDays(30 * $i),
                'status' => 'pending',
            ]);
        }

        return $payable;
    }

    public function test_settles_installment_without_touching_cash_operations(): void
    {
        $admin = User::factory()->admin()->create();
        $payable = $this->createPayableWithInstallments(2);
        $installment = $payable->installments()->first();

        $response = $this->actingAs($admin)->postJson("/api/accounts-payable/installments/{$installment->id}/settle", []);

        $response->assertOk()->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseCount('cash_operations', 0);
    }

    public function test_settles_without_open_cash_register(): void
    {
        $admin = User::factory()->admin()->create();
        $payable = $this->createPayableWithInstallments(1);
        $installment = $payable->installments()->first();

        $response = $this->actingAs($admin)->postJson("/api/accounts-payable/installments/{$installment->id}/settle", []);

        $response->assertOk();
        $this->assertEquals('paid', $payable->fresh()->status->value);
    }

    public function test_cannot_settle_already_paid_installment(): void
    {
        $admin = User::factory()->admin()->create();
        $payable = $this->createPayableWithInstallments(1);
        $installment = $payable->installments()->first();

        $this->actingAs($admin)->postJson("/api/accounts-payable/installments/{$installment->id}/settle", [])->assertOk();

        $response = $this->actingAs($admin)->postJson("/api/accounts-payable/installments/{$installment->id}/settle", []);

        $response->assertStatus(422);
    }

    public function test_cashier_cannot_settle_payable_installment(): void
    {
        $cashier = User::factory()->cashier()->create();
        $payable = $this->createPayableWithInstallments(1);
        $installment = $payable->installments()->first();

        $response = $this->actingAs($cashier)->postJson("/api/accounts-payable/installments/{$installment->id}/settle", []);

        $response->assertStatus(403);
    }
}
