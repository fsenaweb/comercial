<?php

namespace Tests\Feature\Report;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesByDayReportTest extends TestCase
{
    use RefreshDatabase;

    private function createSale(array $overrides = []): Sale
    {
        $seller = $overrides['seller_id'] ?? null;
        unset($overrides['seller_id']);

        $sale = Sale::create(array_merge([
            'seller_id' => $seller ?? User::factory()->create()->id,
            'subtotal' => 100,
            'total' => 100,
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
        $this->getJson('/api/reports/sales-by-day')->assertUnauthorized();
    }

    public function test_aggregates_only_completed_sales_grouped_by_day(): void
    {
        $user = User::factory()->create();

        $this->createSale(['total' => 100, 'created_at' => '2026-07-10 10:00:00']);
        $this->createSale(['total' => 50, 'created_at' => '2026-07-10 15:00:00']);
        $this->createSale(['total' => 200, 'created_at' => '2026-07-11 09:00:00']);
        $this->createSale(['total' => 999, 'status' => SaleStatus::Pending, 'created_at' => '2026-07-10 11:00:00']);
        $this->createSale(['total' => 999, 'status' => SaleStatus::Canceled, 'created_at' => '2026-07-10 11:00:00']);

        $response = $this->actingAs($user)->getJson('/api/reports/sales-by-day?date_from=2026-07-01&date_to=2026-07-31');

        $response->assertOk();
        $data = collect($response->json('data'))->keyBy('date');

        $this->assertEquals(2, $data['2026-07-10']['sales_count']);
        $this->assertEquals('150.00', $data['2026-07-10']['total']);
        $this->assertEquals(1, $data['2026-07-11']['sales_count']);
        $this->assertEquals('200.00', $data['2026-07-11']['total']);
    }

    public function test_filters_by_seller(): void
    {
        $user = User::factory()->create();
        $seller = User::factory()->create();
        $otherSeller = User::factory()->create();

        $this->createSale(['seller_id' => $seller->id, 'total' => 100, 'created_at' => '2026-07-10 10:00:00']);
        $this->createSale(['seller_id' => $otherSeller->id, 'total' => 500, 'created_at' => '2026-07-10 10:00:00']);

        $response = $this->actingAs($user)->getJson("/api/reports/sales-by-day?date_from=2026-07-01&date_to=2026-07-31&seller_id={$seller->id}");

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('100.00', $data[0]['total']);
    }
}
