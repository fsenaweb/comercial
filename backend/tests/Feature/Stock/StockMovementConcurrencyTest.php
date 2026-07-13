<?php

namespace Tests\Feature\Stock;

use App\Models\ProductVariation;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Mesmo padrão do SaleConcurrencyTest: DatabaseMigrations porque os dois
 * processos disparados abaixo rodam em conexões de banco separadas da
 * conexão de teste. Garante que o lockForUpdate() do
 * RegisterStockEntryAction serializa duas entradas concorrentes da mesma
 * variação — sem isso, as duas leriam o mesmo current_quantity antes de
 * incrementar (lost update).
 */
class StockMovementConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_concurrent_stock_entries_of_the_same_variation_do_not_lose_updates(): void
    {
        $user = User::factory()->admin()->create();
        $variation = ProductVariation::factory()->create(['current_quantity' => 10]);

        $env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql',
            'DB_DATABASE' => 'comercial_testing',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ];

        $command = fn () => ['php', 'artisan', 'test:concurrent-stock-entry', (string) $variation->id, (string) $user->id, '5'];

        $p1 = Process::env($env)->path(base_path())->start($command());
        $p2 = Process::env($env)->path(base_path())->start($command());

        $result1 = $p1->wait();
        $result2 = $p2->wait();

        $this->assertTrue($result1->successful(), $result1->errorOutput());
        $this->assertTrue($result2->successful(), $result2->errorOutput());

        $this->assertEquals(20, $variation->fresh()->current_quantity);
        $this->assertEquals(2, StockMovement::where('product_variation_id', $variation->id)->count());
    }
}
