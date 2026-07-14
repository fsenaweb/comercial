<?php

namespace Tests\Feature\Report;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesBySellerReportTest extends TestCase
{
    use RefreshDatabase;

    private function createSale(int $sellerId, float $total, array $overrides = []): Sale
    {
        $sale = Sale::create(array_merge([
            'seller_id' => $sellerId,
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

    public function test_guest_cannot_access(): void
    {
        $this->getJson('/api/reports/sales-by-seller')->assertUnauthorized();
    }

    public function test_aggregates_sales_count_and_total_per_seller(): void
    {
        $user = User::factory()->create();
        $sellerA = User::factory()->create(['name' => 'Vendedor A']);
        $sellerB = User::factory()->create(['name' => 'Vendedor B']);

        $this->createSale($sellerA->id, 100);
        $this->createSale($sellerA->id, 50);
        $this->createSale($sellerB->id, 200);
        $this->createSale($sellerA->id, 999, ['status' => SaleStatus::Pending]);
        $this->createSale($sellerA->id, 999, ['status' => SaleStatus::Canceled]);

        $response = $this->actingAs($user)->getJson('/api/reports/sales-by-seller');

        $response->assertOk();
        $data = collect($response->json('data'))->keyBy('seller_id');

        $this->assertEquals(2, $data[$sellerA->id]['sales_count']);
        $this->assertEquals('150.00', $data[$sellerA->id]['total']);
        $this->assertEquals(1, $data[$sellerB->id]['sales_count']);
        $this->assertEquals('200.00', $data[$sellerB->id]['total']);
    }

    public function test_filters_by_period(): void
    {
        $user = User::factory()->create();
        $seller = User::factory()->create();

        $this->createSale($seller->id, 100, ['created_at' => '2026-06-01 10:00:00']);
        $this->createSale($seller->id, 300, ['created_at' => '2026-07-10 10:00:00']);

        $response = $this->actingAs($user)->getJson('/api/reports/sales-by-seller?date_from=2026-07-01&date_to=2026-07-31');

        $data = collect($response->json('data'))->keyBy('seller_id');
        $this->assertEquals('300.00', $data[$seller->id]['total']);
    }
}
