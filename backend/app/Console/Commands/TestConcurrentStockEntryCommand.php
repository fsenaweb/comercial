<?php

namespace App\Console\Commands;

use App\Actions\Stock\RegisterStockEntryAction;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Comando de apoio exclusivo para o teste de concorrência de estoque
 * (tests/Feature/Stock/StockMovementConcurrencyTest.php). Mesmo raciocínio
 * do test:concurrent-sale: exercita o lockForUpdate() do
 * RegisterStockEntryAction a partir de um processo PHP real e separado.
 */
class TestConcurrentStockEntryCommand extends Command
{
    protected $signature = 'test:concurrent-stock-entry {variationId} {userId} {quantity}';

    protected $description = 'Registra uma entrada de estoque a partir de um processo separado (uso exclusivo em testes de concorrência)';

    public function handle(RegisterStockEntryAction $action): int
    {
        if (! app()->environment('testing')) {
            $this->error('Este comando só pode ser executado no ambiente de testes.');

            return self::FAILURE;
        }

        $user = User::find((int) $this->argument('userId'));

        $movement = $action->execute([
            'product_variation_id' => (int) $this->argument('variationId'),
            'quantity' => (int) $this->argument('quantity'),
            'origin' => 'entrada concorrente (teste)',
        ], $user);

        $this->line(json_encode(['movement_id' => $movement->id]));

        return self::SUCCESS;
    }
}
