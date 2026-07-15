<?php

namespace Tests\Feature\AccountsReceivable;

use App\Models\AccountsReceivable;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterAccountPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function createAccountWithDebit(float $amount = 100): AccountsReceivable
    {
        $customer = Customer::factory()->create();
        $admin = User::factory()->admin()->create();
        $account = AccountsReceivable::create(['customer_id' => $customer->id, 'created_by' => $admin->id]);
        \App\Models\AccountEntry::create([
            'accounts_receivable_id' => $account->id,
            'type' => 'purchase',
            'amount' => $amount,
            'description' => 'Compra do mês',
            'created_by' => $admin->id,
        ]);

        return $account;
    }

    public function test_registers_a_partial_payment_and_updates_balance(): void
    {
        $cashier = User::factory()->cashier()->create();
        CashRegister::factory()->open()->create();
        $paymentMethod = PaymentMethod::factory()->create();
        $account = $this->createAccountWithDebit(100);

        $response = $this->actingAs($cashier)->postJson("/api/accounts-receivable/{$account->id}/payments", [
            'amount' => 40,
            'payment_method_id' => $paymentMethod->id,
        ]);

        $response->assertCreated()->assertJsonPath('data.type', 'payment');
        $this->assertEquals('60.00', $account->fresh()->balance());

        $this->assertDatabaseHas('cash_operations', [
            'origin' => 'accounts_receivable',
            'amount' => '40.00',
        ]);
    }

    public function test_full_payment_zeroes_the_balance(): void
    {
        $cashier = User::factory()->cashier()->create();
        CashRegister::factory()->open()->create();
        $paymentMethod = PaymentMethod::factory()->create();
        $account = $this->createAccountWithDebit(100);

        $this->actingAs($cashier)->postJson("/api/accounts-receivable/{$account->id}/payments", [
            'amount' => 100,
            'payment_method_id' => $paymentMethod->id,
        ])->assertCreated();

        $this->assertEquals('0.00', $account->fresh()->balance());
    }

    public function test_rejects_payment_greater_than_balance(): void
    {
        $cashier = User::factory()->cashier()->create();
        CashRegister::factory()->open()->create();
        $paymentMethod = PaymentMethod::factory()->create();
        $account = $this->createAccountWithDebit(100);

        $response = $this->actingAs($cashier)->postJson("/api/accounts-receivable/{$account->id}/payments", [
            'amount' => 150,
            'payment_method_id' => $paymentMethod->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['amount']);
    }

    public function test_cannot_pay_without_open_cash_register(): void
    {
        $cashier = User::factory()->cashier()->create();
        $paymentMethod = PaymentMethod::factory()->create();
        $account = $this->createAccountWithDebit(100);

        $response = $this->actingAs($cashier)->postJson("/api/accounts-receivable/{$account->id}/payments", [
            'amount' => 40,
            'payment_method_id' => $paymentMethod->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_seller_cannot_register_payment(): void
    {
        $seller = User::factory()->create();
        CashRegister::factory()->open()->create();
        $paymentMethod = PaymentMethod::factory()->create();
        $account = $this->createAccountWithDebit(100);

        $response = $this->actingAs($seller)->postJson("/api/accounts-receivable/{$account->id}/payments", [
            'amount' => 40,
            'payment_method_id' => $paymentMethod->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_removing_accounts_receivable_cash_operation_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        CashRegister::factory()->open()->create();
        $paymentMethod = PaymentMethod::factory()->create();
        $account = $this->createAccountWithDebit(100);

        $this->actingAs($admin)->postJson("/api/accounts-receivable/{$account->id}/payments", [
            'amount' => 40,
            'payment_method_id' => $paymentMethod->id,
        ])->assertCreated();

        $operation = \App\Models\CashOperation::where('origin', 'accounts_receivable')->firstOrFail();

        $response = $this->actingAs($admin)->deleteJson("/api/cash-registers/operations/{$operation->id}");

        $response->assertStatus(422);
    }
}
