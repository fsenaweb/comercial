<?php

namespace Tests\Feature\Report;

use App\Enums\SaleStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesByCategoryReportTest extends TestCase
{
    use RefreshDatabase;

    private function createSaleWithItem(ProductVariation $variation, int $quantity, float $total): void
    {
        $sale = Sale::create([
            'seller_id' => User::factory()->create()->id,
            'subtotal' => $total,
            'total' => $total,
            'status' => SaleStatus::Completed,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_variation_id' => $variation->id,
            'quantity' => $quantity,
            'unit_price' => $total / $quantity,
            'total' => $total,
        ]);
    }

    public function test_guest_cannot_access(): void
    {
        $this->getJson('/api/reports/sales-by-category')->assertUnauthorized();
    }

    public function test_aggregates_quantity_and_total_per_category(): void
    {
        $user = User::factory()->create();
        $categoryA = Category::factory()->create(['name' => 'Parafusos']);
        $categoryB = Category::factory()->create(['name' => 'Ferramentas']);

        $variationA = ProductVariation::factory()->create(['product_id' => Product::factory()->create(['category_id' => $categoryA->id])->id]);
        $variationB = ProductVariation::factory()->create(['product_id' => Product::factory()->create(['category_id' => $categoryB->id])->id]);

        $this->createSaleWithItem($variationA, 4, 40);
        $this->createSaleWithItem($variationA, 2, 20);
        $this->createSaleWithItem($variationB, 1, 100);

        $response = $this->actingAs($user)->getJson('/api/reports/sales-by-category');

        $response->assertOk();
        $data = collect($response->json('data'))->keyBy('category_id');

        $this->assertEquals(6, $data[$categoryA->id]['quantity_sold']);
        $this->assertEquals('60.00', $data[$categoryA->id]['total']);
        $this->assertEquals(1, $data[$categoryB->id]['quantity_sold']);
        $this->assertEquals('100.00', $data[$categoryB->id]['total']);
    }
}
