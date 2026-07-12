<?php

namespace Tests\Feature\Subcategory;

use App\Models\Category;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubcategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_subcategories(): void
    {
        $this->getJson('/api/subcategories')->assertStatus(401);
    }

    public function test_authenticated_user_can_list_subcategories(): void
    {
        Subcategory::factory()->count(2)->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/subcategories');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_admin_can_create_subcategory(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/subcategories', [
            'category_id' => $category->id,
            'name' => 'Refrigerantes',
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'Refrigerantes');
        $this->assertDatabaseHas('subcategories', ['name' => 'Refrigerantes', 'category_id' => $category->id]);
    }

    public function test_seller_cannot_create_subcategory(): void
    {
        $seller = User::factory()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($seller)->postJson('/api/subcategories', [
            'category_id' => $category->id,
            'name' => 'Refrigerantes',
        ]);

        $response->assertStatus(403);
    }

    public function test_create_requires_valid_category(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/subcategories', [
            'category_id' => 9999,
            'name' => 'Refrigerantes',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['category_id']);
    }

    public function test_admin_can_update_subcategory(): void
    {
        $admin = User::factory()->admin()->create();
        $subcategory = Subcategory::factory()->create();

        $response = $this->actingAs($admin)->putJson("/api/subcategories/{$subcategory->id}", [
            'category_id' => $subcategory->category_id,
            'name' => 'Atualizada',
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'Atualizada');
    }

    public function test_admin_can_delete_subcategory(): void
    {
        $admin = User::factory()->admin()->create();
        $subcategory = Subcategory::factory()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/subcategories/{$subcategory->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('subcategories', ['id' => $subcategory->id]);
    }
}
