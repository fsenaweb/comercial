<?php

namespace Tests\Feature\Sale;

use App\Models\CashRegister;
use App\Models\PaymentMethod;
use App\Models\ProductVariation;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Primeiro teste de concorrência do projeto. Usa DatabaseMigrations (não
 * RefreshDatabase) porque os dois processos disparados abaixo rodam em
 * conexões de banco totalmente separadas da conexão de teste — presos numa
 * transação de teste não commitada, eles não veriam o registro criado aqui
 * (mesmo motivo do BackupRestoreTest usar pg_dump como processo externo).
 *
 * Garante que o lockForUpdate() do RegisterSaleAction serializa duas vendas
 * concorrentes do mesmo item — sem isso, as duas leriam o mesmo
 * current_quantity antes de decrementar (lost update).
 */
class SaleConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_concurrent_sales_of_the_same_item_do_not_lose_stock_updates(): void
    {
        $cashRegister = CashRegister::factory()->open()->create();
        $user = User::factory()->admin()->create();
        PaymentMethod::factory()->create(['active_on_pos' => true]);
        $variation = ProductVariation::factory()->create(['current_quantity' => 10]);

        $env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql',
            'DB_DATABASE' => 'comercial_testing',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ];

        $command = fn () => ['php', 'artisan', 'test:concurrent-sale', (string) $variation->id, (string) $cashRegister->id, (string) $user->id, '3'];

        $p1 = Process::env($env)->path(base_path())->start($command());
        $p2 = Process::env($env)->path(base_path())->start($command());

        $result1 = $p1->wait();
        $result2 = $p2->wait();

        $this->assertTrue($result1->successful(), $result1->errorOutput());
        $this->assertTrue($result2->successful(), $result2->errorOutput());

        $this->assertEquals(2, Sale::count());
        $this->assertEquals(4, $variation->fresh()->current_quantity);

        $numbers = Sale::pluck('number')->all();
        $this->assertCount(2, array_unique($numbers));

        $this->assertEquals(6, \App\Models\StockMovement::where('product_variation_id', $variation->id)->sum('quantity'));
    }
}
