<?php

namespace Tests\Feature\Category;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_categories(): void
    {
        $this->getJson('/api/categories')->assertStatus(401);
    }

    public function test_authenticated_user_can_list_categories(): void
    {
        Category::factory()->count(3)->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/categories');

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_admin_can_create_category(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/categories', [
            'name' => 'Bebidas',
            'description' => 'Categoria de bebidas',
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'Bebidas');
        $this->assertDatabaseHas('categories', ['name' => 'Bebidas']);
    }

    public function test_seller_cannot_create_category(): void
    {
        $seller = User::factory()->create();

        $response = $this->actingAs($seller)->postJson('/api/categories', ['name' => 'Bebidas']);

        $response->assertStatus(403);
    }

    public function test_create_requires_name(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/categories', []);

        $response->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    public function test_admin_can_update_category(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($admin)->putJson("/api/categories/{$category->id}", [
            'name' => 'Atualizada',
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'Atualizada');
    }

    public function test_admin_can_delete_category(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/categories/{$category->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }

    public function test_seller_cannot_delete_category(): void
    {
        $seller = User::factory()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($seller)->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(403);
    }
}
