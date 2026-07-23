<?php

namespace Tests\Feature\Product;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_variation_and_initial_stock_is_registered(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($admin)->postJson("/api/products/{$product->id}/variations", [
            'code' => 'SKU-0001',
            'cost_price' => 10,
            'sale_price' => 20,
            'initial_quantity' => 15,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'SKU-0001')
            ->assertJsonPath('data.current_quantity', 15);

        $variation = ProductVariation::where('code', 'SKU-0001')->firstOrFail();

        $this->assertDatabaseHas('stock_movements', [
            'product_variation_id' => $variation->id,
            'type' => 'adjustment',
            'quantity' => 15,
            'origin' => 'estoque inicial',
            'user_id' => $admin->id,
        ]);
    }

    public function test_seller_cannot_create_variation(): void
    {
        $seller = User::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($seller)->postJson("/api/products/{$product->id}/variations", [
            'code' => 'SKU-0001',
            'cost_price' => 10,
            'sale_price' => 20,
            'initial_quantity' => 15,
        ]);

        $response->assertStatus(403);
    }

    public function test_create_requires_unique_code(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        ProductVariation::factory()->create(['product_id' => $product->id, 'code' => 'SKU-0001']);

        $response = $this->actingAs($admin)->postJson("/api/products/{$product->id}/variations", [
            'code' => 'SKU-0001',
            'cost_price' => 10,
            'sale_price' => 20,
            'initial_quantity' => 15,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    public function test_admin_can_update_variation_without_touching_quantity(): void
    {
        $admin = User::factory()->admin()->create();
        $variation = ProductVariation::factory()->create(['current_quantity' => 15]);

        $response = $this->actingAs($admin)->putJson(
            "/api/products/{$variation->product_id}/variations/{$variation->id}",
            [
                'code' => $variation->code,
                'cost_price' => 12,
                'sale_price' => 25,
            ]
        );

        $response->assertOk()->assertJsonPath('data.sale_price', '25.00');
        $this->assertDatabaseHas('product_variations', ['id' => $variation->id, 'current_quantity' => 15]);
    }

    public function test_admin_can_delete_variation(): void
    {
        $admin = User::factory()->admin()->create();
        $variation = ProductVariation::factory()->create();

        $response = $this->actingAs($admin)->deleteJson(
            "/api/products/{$variation->product_id}/variations/{$variation->id}"
        );

        $response->assertNoContent();
        $this->assertSoftDeleted('product_variations', ['id' => $variation->id]);
    }
}
