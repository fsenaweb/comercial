<?php

namespace Tests\Feature\Expense;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettleExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_settle_pending_expense(): void
    {
        $admin = User::factory()->admin()->create();
        $expense = Expense::create([
            'description' => 'Internet',
            'amount' => 200,
            'due_date' => now()->addDays(10),
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->postJson("/api/expenses/{$expense->id}/settle");

        $response->assertOk()->assertJsonPath('data.status', 'paid');
        $this->assertDatabaseCount('cash_operations', 0);
    }

    public function test_cannot_settle_already_paid_expense(): void
    {
        $admin = User::factory()->admin()->create();
        $expense = Expense::create([
            'description' => 'Internet',
            'amount' => 200,
            'due_date' => now()->addDays(10),
            'status' => 'paid',
            'paid_at' => now(),
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->postJson("/api/expenses/{$expense->id}/settle");

        $response->assertStatus(422);
    }

    public function test_cashier_cannot_settle_expense(): void
    {
        $admin = User::factory()->admin()->create();
        $cashier = User::factory()->cashier()->create();
        $expense = Expense::create([
            'description' => 'Internet',
            'amount' => 200,
            'due_date' => now()->addDays(10),
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($cashier)->postJson("/api/expenses/{$expense->id}/settle");

        $response->assertStatus(403);
    }
}
