<?php

namespace Tests\Feature\Brand;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_brands(): void
    {
        $this->getJson('/api/brands')->assertStatus(401);
    }

    public function test_authenticated_user_can_list_brands(): void
    {
        Brand::factory()->count(2)->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/brands');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_admin_can_create_brand(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/brands', ['name' => 'Coca-Cola']);

        $response->assertCreated()->assertJsonPath('data.name', 'Coca-Cola');
        $this->assertDatabaseHas('brands', ['name' => 'Coca-Cola']);
    }

    public function test_seller_cannot_create_brand(): void
    {
        $seller = User::factory()->create();

        $response = $this->actingAs($seller)->postJson('/api/brands', ['name' => 'Coca-Cola']);

        $response->assertStatus(403);
    }

    public function test_create_requires_name(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/brands', []);

        $response->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    public function test_admin_can_update_brand(): void
    {
        $admin = User::factory()->admin()->create();
        $brand = Brand::factory()->create();

        $response = $this->actingAs($admin)->putJson("/api/brands/{$brand->id}", ['name' => 'Atualizada']);

        $response->assertOk()->assertJsonPath('data.name', 'Atualizada');
    }

    public function test_admin_can_delete_brand(): void
    {
        $admin = User::factory()->admin()->create();
        $brand = Brand::factory()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/brands/{$brand->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('brands', ['id' => $brand->id]);
    }
}
