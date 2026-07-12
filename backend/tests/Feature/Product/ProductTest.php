<?php

namespace Tests\Feature\Product;

use App\Models\Category;
use App\Models\Product;
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

    public function test_admin_can_delete_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/products/{$product->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }
}
