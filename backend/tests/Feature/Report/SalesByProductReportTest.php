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

class SalesByProductReportTest extends TestCase
{
    use RefreshDatabase;

    private function createSaleWithItem(ProductVariation $variation, int $quantity, float $total, array $saleOverrides = []): void
    {
        $sale = Sale::create(array_merge([
            'seller_id' => User::factory()->create()->id,
            'subtotal' => $total,
            'total' => $total,
            'status' => SaleStatus::Completed,
        ], $saleOverrides));

        if (isset($saleOverrides['created_at'])) {
            $sale->created_at = $saleOverrides['created_at'];
            $sale->save();
        }

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
        $this->getJson('/api/reports/sales-by-product')->assertUnauthorized();
    }

    public function test_aggregates_quantity_and_total_per_product_from_completed_sales_only(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Parafuso Sextavado']);
        $variation = ProductVariation::factory()->create(['product_id' => $product->id, 'code' => 'PRF-SEX-01']);

        $this->createSaleWithItem($variation, 5, 50);
        $this->createSaleWithItem($variation, 3, 30);
        $this->createSaleWithItem($variation, 100, 1000, ['status' => SaleStatus::Pending]);
        $this->createSaleWithItem($variation, 100, 1000, ['status' => SaleStatus::Canceled]);

        $response = $this->actingAs($user)->getJson('/api/reports/sales-by-product');

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('product_id', $product->id);

        $this->assertEquals(8, $row['quantity_sold']);
        $this->assertEquals('80.00', $row['total']);
        // Código do produto precisa aparecer no relatório de vendas — usado
        // pelo cliente para conferência cruzada com o sistema fiscal (pedido
        // do usuário, ver docs/11-migracao-sistema-legado.md).
        $this->assertEquals('PRF-SEX-01', $row['code']);
    }

    public function test_filters_by_category(): void
    {
        $user = User::factory()->create();
        $categoryA = Category::factory()->create();
        $categoryB = Category::factory()->create();

        $productA = Product::factory()->create(['category_id' => $categoryA->id]);
        $variationA = ProductVariation::factory()->create(['product_id' => $productA->id]);

        $productB = Product::factory()->create(['category_id' => $categoryB->id]);
        $variationB = ProductVariation::factory()->create(['product_id' => $productB->id]);

        $this->createSaleWithItem($variationA, 1, 10);
        $this->createSaleWithItem($variationB, 1, 20);

        $response = $this->actingAs($user)->getJson("/api/reports/sales-by-product?category_id={$categoryA->id}");

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($productA->id, $data[0]['product_id']);
    }
}
