<?php

namespace Tests\Feature\Stock;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KardexListTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_authenticated_user_can_list_stock_movements(): void
    {
        $seller = User::factory()->create();
        StockMovement::factory()->count(3)->create();

        $response = $this->actingAs($seller)->getJson('/api/stock-movements');

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_can_filter_by_product_variation(): void
    {
        $admin = User::factory()->admin()->create();
        $variation = ProductVariation::factory()->create();
        StockMovement::factory()->create(['product_variation_id' => $variation->id]);
        StockMovement::factory()->count(2)->create();

        $response = $this->actingAs($admin)->getJson("/api/stock-movements?product_variation_id={$variation->id}");

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_can_filter_by_type(): void
    {
        $admin = User::factory()->admin()->create();
        StockMovement::factory()->create(['type' => StockMovementType::In]);
        StockMovement::factory()->create(['type' => StockMovementType::Adjustment]);

        $response = $this->actingAs($admin)->getJson('/api/stock-movements?type=in');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'in');
    }

    public function test_can_search_by_product_name_or_code(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['name' => 'Parafuso Sextavado']);
        $variation = ProductVariation::factory()->create(['product_id' => $product->id, 'code' => 'PRF-001']);
        StockMovement::factory()->create(['product_variation_id' => $variation->id]);
        StockMovement::factory()->create();

        $response = $this->actingAs($admin)->getJson('/api/stock-movements?search=Sextavado');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_guest_cannot_list_stock_movements(): void
    {
        $this->getJson('/api/stock-movements')->assertStatus(401);
    }
}
