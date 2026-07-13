<?php

namespace App\Console\Commands;

use App\Actions\Sale\RegisterSaleAction;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Comando de apoio exclusivo para o teste de concorrência de vendas
 * (tests/Feature/Sale/SaleConcurrencyTest.php). Chama a Action diretamente
 * a partir de um processo PHP real e separado, para exercitar o
 * lockForUpdate() contra uma conexão de banco de verdade — o que uma
 * segunda chamada dentro do mesmo processo de teste (RefreshDatabase)
 * não conseguiria simular.
 */
class TestConcurrentSaleCommand extends Command
{
    protected $signature = 'test:concurrent-sale {variationId} {cashRegisterId} {userId} {quantity}';

    protected $description = 'Registra uma venda de teste a partir de um processo separado (uso exclusivo em testes de concorrência)';

    public function handle(RegisterSaleAction $action): int
    {
        if (! app()->environment('testing')) {
            $this->error('Este comando só pode ser executado no ambiente de testes.');

            return self::FAILURE;
        }

        $user = User::find((int) $this->argument('userId'));
        $paymentMethod = PaymentMethod::first();

        $sale = $action->execute([
            'payment_method_id' => $paymentMethod->id,
            'items' => [
                ['product_variation_id' => (int) $this->argument('variationId'), 'quantity' => (int) $this->argument('quantity')],
            ],
        ], $user);

        $this->line(json_encode(['sale_id' => $sale->id, 'number' => $sale->number]));

        return self::SUCCESS;
    }
}
