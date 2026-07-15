<?php

namespace Tests\Feature\AccountsReceivable;

use App\Models\AccountEntry;
use App\Models\AccountsReceivable;
use App\Models\CashOperation;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Garante que o lockForUpdate() do RegisterAccountPaymentAction serializa
 * dois pagamentos concorrentes na mesma conta — sem isso, os dois
 * processos poderiam ler o mesmo saldo antes de qualquer um gravar seu
 * pagamento, deixando os dois passarem mesmo que juntos ultrapassem o
 * saldo devedor. Mesmo padrão de tests/Feature/Sale/SaleConcurrencyTest.php.
 */
class RegisterAccountPaymentConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_two_concurrent_payments_together_cannot_exceed_the_balance(): void
    {
        CashRegister::factory()->open()->create();
        $user = User::factory()->admin()->create();
        $paymentMethod = PaymentMethod::factory()->create();
        $customer = Customer::factory()->create();

        $account = AccountsReceivable::create(['customer_id' => $customer->id, 'created_by' => $user->id]);
        AccountEntry::create([
            'accounts_receivable_id' => $account->id,
            'type' => 'purchase',
            'amount' => 100,
            'description' => 'Compra do mês',
            'created_by' => $user->id,
        ]);

        $env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql',
            'DB_DATABASE' => 'comercial_testing',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ];

        // Cada processo tenta pagar 70 — juntos passariam de 100 se não serializassem.
        $command = fn () => ['php', 'artisan', 'test:concurrent-account-payment', (string) $account->id, (string) $user->id, (string) $paymentMethod->id, '70'];

        $p1 = Process::env($env)->path(base_path())->start($command());
        $p2 = Process::env($env)->path(base_path())->start($command());

        $result1 = $p1->wait();
        $result2 = $p2->wait();

        $this->assertTrue($result1->successful(), $result1->errorOutput());
        $this->assertTrue($result2->successful(), $result2->errorOutput());

        $this->assertEquals('30.00', $account->fresh()->balance());
        $this->assertEquals(1, CashOperation::where('origin', 'accounts_receivable')->count());
        $this->assertEquals(1, AccountEntry::where('type', 'payment')->count());
    }
}
