<?php

namespace App\Console\Commands;

use App\Actions\AccountsReceivable\RegisterAccountPaymentAction;
use App\Models\AccountsReceivable;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Comando de apoio exclusivo para o teste de concorrência de pagamento de
 * crediário (tests/Feature/AccountsReceivable/RegisterAccountPaymentConcurrencyTest.php).
 * Mesmo raciocínio do test:concurrent-sale: exercita o lockForUpdate() do
 * RegisterAccountPaymentAction a partir de um processo PHP real e separado,
 * garantindo que dois pagamentos simultâneos não consigam, juntos,
 * ultrapassar o saldo devedor da conta.
 */
class TestConcurrentAccountPaymentCommand extends Command
{
    protected $signature = 'test:concurrent-account-payment {accountId} {userId} {paymentMethodId} {amount}';

    protected $description = 'Registra um pagamento de crediário a partir de um processo separado (uso exclusivo em testes de concorrência)';

    public function handle(RegisterAccountPaymentAction $action): int
    {
        if (! app()->environment('testing')) {
            $this->error('Este comando só pode ser executado no ambiente de testes.');

            return self::FAILURE;
        }

        $account = AccountsReceivable::find((int) $this->argument('accountId'));
        $user = User::find((int) $this->argument('userId'));

        try {
            $action->execute($account, [
                'payment_method_id' => (int) $this->argument('paymentMethodId'),
                'amount' => (float) $this->argument('amount'),
            ], $user);
        } catch (ValidationException) {
            $this->line(json_encode(['paid' => false]));

            return self::SUCCESS;
        }

        $this->line(json_encode(['paid' => true]));

        return self::SUCCESS;
    }
}
