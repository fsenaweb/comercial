<?php

namespace Tests\Feature\Report;

use App\Enums\SaleStatus;
use App\Models\ProductVariation;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardSummaryReportTest extends TestCase
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
        $this->getJson('/api/reports/dashboard-summary')->assertUnauthorized();
    }

    public function test_summarizes_today_low_stock_and_monthly_series(): void
    {
        $user = User::factory()->create();
        $seller = User::factory()->create(['name' => 'Vendedor Hoje']);

        $this->createSale($seller->id, 100, ['created_at' => Carbon::today()->setTime(9, 0)]);
        $this->createSale($seller->id, 50, ['created_at' => Carbon::today()->setTime(14, 0)]);
        $this->createSale($seller->id, 500, ['created_at' => Carbon::yesterday()->setTime(10, 0)]);
        $this->createSale($seller->id, 999, ['status' => SaleStatus::Pending, 'created_at' => Carbon::today()->setTime(10, 0)]);

        ProductVariation::factory()->create(['current_quantity' => 1, 'min_quantity' => 5]);
        ProductVariation::factory()->create(['current_quantity' => 10, 'min_quantity' => 5]);

        $response = $this->actingAs($user)->getJson('/api/reports/dashboard-summary');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertEquals(2, $data['today_sales_count']);
        $this->assertEquals('150.00', $data['today_total']);
        $this->assertEquals(1, $data['low_stock_count']);

        $sellerToday = collect($data['sales_by_seller_today'])->firstWhere('seller_id', $seller->id);
        $this->assertEquals('150.00', $sellerToday['total']);

        $currentMonth = Carbon::today()->format('Y-m');
        $monthlyCount = collect($data['monthly_sales_count'])->firstWhere('month', $currentMonth);
        $this->assertGreaterThanOrEqual(3, $monthlyCount['count']);
    }
}
