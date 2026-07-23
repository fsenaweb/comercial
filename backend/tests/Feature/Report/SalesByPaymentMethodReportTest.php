<?php

namespace Tests\Feature\Report;

use App\Enums\SaleStatus;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesByPaymentMethodReportTest extends TestCase
{
    use RefreshDatabase;

    private function createSale(float $total, array $overrides = []): Sale
    {
        $sale = Sale::create(array_merge([
            'seller_id' => User::factory()->create()->id,
            'subtotal' => $total,
            'total' => $total,
            'status' => SaleStatus::Completed,
        ], $overrides));

        if (isset($overrides['created_at'])) {
            $sale->created_at = $overrides['created_at'];
            $sale->save();
        }

        return $sale;
    }

    public function test_lists_individual_transactions_per_payment_method(): void
    {
        $user = User::factory()->create();
        $credit = PaymentMethod::factory()->create(['name' => 'Cartão de Crédito']);
        $cash = PaymentMethod::factory()->create(['name' => 'Dinheiro']);

        $saleA = $this->createSale(10);
        SalePayment::create(['sale_id' => $saleA->id, 'payment_method_id' => $credit->id, 'amount' => 10]);
        $saleB = $this->createSale(20);
        SalePayment::create(['sale_id' => $saleB->id, 'payment_method_id' => $credit->id, 'amount' => 20]);
        $saleC = $this->createSale(40);
        SalePayment::create(['sale_id' => $saleC->id, 'payment_method_id' => $credit->id, 'amount' => 40]);
        $saleD = $this->createSale(5);
        SalePayment::create(['sale_id' => $saleD->id, 'payment_method_id' => $cash->id, 'amount' => 5]);

        $response = $this->actingAs($user)->getJson('/api/reports/catalog/vendas_forma_pagamento');

        $response->assertOk();
        $rows = collect($response->json('data.rows'));

        // 3 transações + 1 subtotal do cartão, 1 transação + 1 subtotal do dinheiro.
        $creditRows = $rows->filter(fn ($row) => str_contains((string) $row['payment_method_name'], 'Crédito'));
        $this->assertCount(4, $creditRows);

        $amounts = $creditRows->pluck('amount')->values()->all();
        $this->assertSame(['10,00'.'', '20,00', '40,00', '70,00'], array_map(fn ($v) => str_replace('R$ ', '', $v), $amounts));

        $subtotalRow = $rows->firstWhere('payment_method_name', 'Subtotal — Cartão de Crédito');
        $this->assertNotNull($subtotalRow);
        $this->assertSame('', $subtotalRow['sale_number']);

        $summary = collect($response->json('data.summary'))->keyBy('label');
        $this->assertStringContainsString('3 venda(s)', $summary['Cartão de Crédito']['value']);
        $this->assertStringContainsString('1 venda(s)', $summary['Dinheiro']['value']);
    }

    public function test_transaction_row_includes_sale_number_and_date(): void
    {
        $user = User::factory()->create();
        $pix = PaymentMethod::factory()->create(['name' => 'Pix']);

        $sale = $this->createSale(15, ['created_at' => '2026-07-10 14:30:00']);
        SalePayment::create(['sale_id' => $sale->id, 'payment_method_id' => $pix->id, 'amount' => 15]);

        $response = $this->actingAs($user)->getJson('/api/reports/catalog/vendas_forma_pagamento');

        $rows = collect($response->json('data.rows'));
        $row = $rows->firstWhere('sale_number', $sale->number);

        $this->assertNotNull($row);
        $this->assertSame('10/07/2026 14:30', $row['sale_date']);
        $this->assertSame('R$ 15,00', $row['amount']);
    }

    public function test_split_payment_sale_appears_under_each_method_used(): void
    {
        $user = User::factory()->create();
        $pix = PaymentMethod::factory()->create(['name' => 'Pix']);
        $cash = PaymentMethod::factory()->create(['name' => 'Dinheiro']);

        $sale = $this->createSale(30);
        SalePayment::create(['sale_id' => $sale->id, 'payment_method_id' => $pix->id, 'amount' => 20]);
        SalePayment::create(['sale_id' => $sale->id, 'payment_method_id' => $cash->id, 'amount' => 10]);

        $response = $this->actingAs($user)->getJson('/api/reports/catalog/vendas_forma_pagamento');

        $rows = collect($response->json('data.rows'));
        $this->assertCount(1, $rows->where('payment_method_name', 'Pix')->where('sale_number', $sale->number));
        $this->assertCount(1, $rows->where('payment_method_name', 'Dinheiro')->where('sale_number', $sale->number));
    }

    public function test_excludes_non_completed_sales(): void
    {
        $user = User::factory()->create();
        $method = PaymentMethod::factory()->create();

        $pending = $this->createSale(50, ['status' => SaleStatus::Pending]);
        SalePayment::create(['sale_id' => $pending->id, 'payment_method_id' => $method->id, 'amount' => 50]);

        $response = $this->actingAs($user)->getJson('/api/reports/catalog/vendas_forma_pagamento');

        $this->assertEmpty($response->json('data.rows'));
        $this->assertEmpty($response->json('data.summary'));
    }

    public function test_filters_by_period(): void
    {
        $user = User::factory()->create();
        $method = PaymentMethod::factory()->create(['name' => 'Pix']);

        $outOfRange = $this->createSale(100, ['created_at' => '2026-06-01 10:00:00']);
        SalePayment::create(['sale_id' => $outOfRange->id, 'payment_method_id' => $method->id, 'amount' => 100]);
        $inRange = $this->createSale(300, ['created_at' => '2026-07-10 10:00:00']);
        SalePayment::create(['sale_id' => $inRange->id, 'payment_method_id' => $method->id, 'amount' => 300]);

        $response = $this->actingAs($user)->getJson('/api/reports/catalog/vendas_forma_pagamento?date_from=2026-07-01&date_to=2026-07-31');

        $rows = collect($response->json('data.rows'));
        $this->assertCount(2, $rows); // 1 transação + 1 subtotal
        $this->assertSame($inRange->number, $rows->first()['sale_number']);
    }

    public function test_filters_by_selected_payment_methods(): void
    {
        $user = User::factory()->create();
        $pix = PaymentMethod::factory()->create(['name' => 'Pix']);
        $cash = PaymentMethod::factory()->create(['name' => 'Dinheiro']);

        $saleA = $this->createSale(30);
        SalePayment::create(['sale_id' => $saleA->id, 'payment_method_id' => $pix->id, 'amount' => 30]);
        $saleB = $this->createSale(15);
        SalePayment::create(['sale_id' => $saleB->id, 'payment_method_id' => $cash->id, 'amount' => 15]);

        $response = $this->actingAs($user)->getJson("/api/reports/catalog/vendas_forma_pagamento?payment_method_ids[]={$pix->id}");

        $rows = collect($response->json('data.rows'));
        $this->assertTrue($rows->every(fn ($row) => str_contains((string) $row['payment_method_name'], 'Pix')));
        $summary = collect($response->json('data.summary'))->keyBy('label');
        $this->assertArrayHasKey('Pix', $summary->toArray());
        $this->assertArrayNotHasKey('Dinheiro', $summary->toArray());
    }
}
