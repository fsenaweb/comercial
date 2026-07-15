<?php

namespace Tests\Feature\Finance;

use App\Models\AccountEntry;
use App\Models\AccountsReceivable;
use App\Models\AccountsPayable;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\PayableInstallment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_overview(): void
    {
        $this->getJson('/api/financeiro/overview')->assertStatus(401);
    }

    public function test_aggregates_receivable_balance_and_payables_due_this_month(): void
    {
        $user = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        $supplier = Supplier::factory()->create();

        $account = AccountsReceivable::create(['customer_id' => $customer->id, 'created_by' => $user->id]);
        AccountEntry::create([
            'accounts_receivable_id' => $account->id, 'type' => 'purchase', 'amount' => 150,
            'description' => 'Compras do mês', 'created_by' => $user->id,
        ]);

        $payable = AccountsPayable::create([
            'supplier_id' => $supplier->id, 'description' => 'NF-e 1', 'total_amount' => 200,
            'installments_count' => 1, 'status' => 'open', 'created_by' => $user->id,
        ]);
        PayableInstallment::create([
            'accounts_payable_id' => $payable->id, 'number' => 1, 'amount' => 200,
            'due_date' => now()->startOfMonth()->addDays(5), 'status' => 'pending',
        ]);

        Expense::create([
            'description' => 'Aluguel', 'amount' => 1000, 'due_date' => now()->startOfMonth()->addDays(10),
            'status' => 'pending', 'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/financeiro/overview?month='.now()->format('Y-m'));

        $response->assertOk()
            ->assertJsonPath('data.receivable_balance_total', '150.00')
            ->assertJsonPath('data.payables_due_this_month', '1200.00')
            ->assertJsonCount(1, 'data.top_receivable_balances')
            ->assertJsonCount(2, 'data.top_payables_this_month');
    }

    public function test_overdue_payables_are_flagged(): void
    {
        $user = User::factory()->admin()->create();

        Expense::create([
            'description' => 'Conta atrasada', 'amount' => 80, 'due_date' => now()->subDays(10),
            'status' => 'pending', 'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/financeiro/overview');

        $response->assertOk()
            ->assertJsonPath('data.overdue_count', 1)
            ->assertJsonPath('data.overdue_total', '80.00');
    }
}
