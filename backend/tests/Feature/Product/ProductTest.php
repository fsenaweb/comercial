<?php

namespace Tests\Feature\Product;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_products(): void
    {
        $this->getJson('/api/products')->assertStatus(401);
    }

    public function test_authenticated_user_can_list_products(): void
    {
        Product::factory()->count(2)->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/products');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_listing_is_paginated(): void
    {
        Product::factory()->count(30)->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/products?per_page=10');

        $response->assertOk()->assertJsonCount(10, 'data');
        $this->assertEquals(30, $response->json('meta.total'));
    }

    public function test_listing_filters_by_search_term_matching_name_or_product_code(): void
    {
        $user = User::factory()->create();
        $match = Product::factory()->create(['name' => 'Parafuso Sextavado M8']);
        Product::factory()->create(['name' => 'Arruela de Pressão']);

        $response = $this->actingAs($user)->getJson('/api/products?search=Sextavado');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals($match->id, $response->json('data.0.id'));
    }

    public function test_summary_aggregates_stock_across_all_variations(): void
    {
        $user = User::factory()->create();
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();
        ProductVariation::factory()->create([
            'product_id' => $productA->id,
            'current_quantity' => 10,
            'min_quantity' => 20,
            'sale_price' => 5,
        ]);
        ProductVariation::factory()->create([
            'product_id' => $productB->id,
            'current_quantity' => 0,
            'min_quantity' => null,
            'sale_price' => 3,
        ]);

        $response = $this->actingAs($user)->getJson('/api/products/summary');

        $response->assertOk();
        $this->assertEquals(2, $response->json('data.total_products'));
        $this->assertEquals(10, $response->json('data.total_stock_qty'));
        $this->assertEquals(1, $response->json('data.low_stock_count'));
        $this->assertEquals(1, $response->json('data.no_stock_count'));
    }

    public function test_admin_can_create_product(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = Unit::factory()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/products', [
            'name' => 'Refrigerante Lata',
            'type' => 'product',
            'unit_id' => $unit->id,
            'category_id' => $category->id,
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'Refrigerante Lata');
        $this->assertDatabaseHas('products', ['name' => 'Refrigerante Lata']);
    }

    public function test_admin_can_create_product_with_supplier(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = Unit::factory()->create();
        $category = Category::factory()->create();
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/products', [
            'name' => 'Refrigerante Lata',
            'type' => 'product',
            'unit_id' => $unit->id,
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
        ]);

        $response->assertCreated()->assertJsonPath('data.supplier_id', $supplier->id);
        $this->assertDatabaseHas('products', ['name' => 'Refrigerante Lata', 'supplier_id' => $supplier->id]);
    }

    public function test_create_rejects_invalid_supplier(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = Unit::factory()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/products', [
            'name' => 'Refrigerante Lata',
            'type' => 'product',
            'unit_id' => $unit->id,
            'category_id' => $category->id,
            'supplier_id' => 9999,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['supplier_id']);
    }

    public function test_seller_cannot_create_product(): void
    {
        $seller = User::factory()->create();
        $unit = Unit::factory()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($seller)->postJson('/api/products', [
            'name' => 'Refrigerante Lata',
            'type' => 'product',
            'unit_id' => $unit->id,
            'category_id' => $category->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_create_requires_valid_unit_and_category(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/products', [
            'name' => 'Refrigerante Lata',
            'type' => 'product',
            'unit_id' => 9999,
            'category_id' => 9999,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['unit_id', 'category_id']);
    }

    public function test_admin_can_update_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($admin)->putJson("/api/products/{$product->id}", [
            'name' => 'Atualizado',
            'type' => 'product',
            'unit_id' => $product->unit_id,
            'category_id' => $product->category_id,
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'Atualizado');
    }

    public function test_product_is_active_by_default(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = Unit::factory()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/products', [
            'name' => 'Refrigerante Lata',
            'type' => 'product',
            'unit_id' => $unit->id,
            'category_id' => $category->id,
        ]);

        $response->assertCreated()->assertJsonPath('data.active', true);
        $this->assertDatabaseHas('products', ['name' => 'Refrigerante Lata', 'active' => true]);
    }

    public function test_admin_can_deactivate_a_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['active' => true]);

        $response = $this->actingAs($admin)->putJson("/api/products/{$product->id}", [
            'name' => $product->name,
            'type' => $product->type->value,
            'active' => false,
            'unit_id' => $product->unit_id,
            'category_id' => $product->category_id,
        ]);

        $response->assertOk()->assertJsonPath('data.active', false);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'active' => false]);
    }

    public function test_admin_can_delete_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/products/{$product->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }
}
