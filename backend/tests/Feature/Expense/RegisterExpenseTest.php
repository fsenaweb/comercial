<?php

namespace Tests\Feature\Expense;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_register_pending_expense(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/expenses', [
            'description' => 'Aluguel',
            'category' => 'Aluguel',
            'amount' => 1500,
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('expenses', ['description' => 'Aluguel', 'status' => 'pending']);
    }

    public function test_paid_now_flag_creates_expense_already_paid(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/expenses', [
            'description' => 'Energia',
            'amount' => 350,
            'due_date' => now()->toDateString(),
            'paid_now' => true,
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'paid');
        $this->assertNotNull($response->json('data.paid_at'));
    }

    public function test_cashier_cannot_register_expense(): void
    {
        $cashier = User::factory()->cashier()->create();

        $response = $this->actingAs($cashier)->postJson('/api/expenses', [
            'description' => 'Energia',
            'amount' => 350,
            'due_date' => now()->toDateString(),
        ]);

        $response->assertStatus(403);
    }

    public function test_amount_is_validated(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/expenses', [
            'description' => 'Energia',
            'amount' => 0,
            'due_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['amount']);
    }
}
