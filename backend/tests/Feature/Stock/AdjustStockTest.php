<?php

namespace Tests\Feature\Stock;

use App\Models\ProductVariation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdjustStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_adjust_stock_upward(): void
    {
        $admin = User::factory()->admin()->create();
        $variation = ProductVariation::factory()->create(['current_quantity' => 10]);

        $response = $this->actingAs($admin)->postJson('/api/stock-movements/adjustment', [
            'product_variation_id' => $variation->id,
            'new_quantity' => 15,
            'reason' => 'Contagem de inventário',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'adjustment')
            ->assertJsonPath('data.quantity', 5)
            ->assertJsonPath('data.origin', 'Contagem de inventário');

        $this->assertEquals(15, $variation->fresh()->current_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_variation_id' => $variation->id,
            'type' => 'adjustment',
            'quantity' => 5,
        ]);
    }

    public function test_admin_can_adjust_stock_downward(): void
    {
        $admin = User::factory()->admin()->create();
        $variation = ProductVariation::factory()->create(['current_quantity' => 10]);

        $response = $this->actingAs($admin)->postJson('/api/stock-movements/adjustment', [
            'product_variation_id' => $variation->id,
            'new_quantity' => 3,
            'reason' => 'Avaria',
        ]);

        $response->assertCreated()->assertJsonPath('data.quantity', -7);
        $this->assertEquals(3, $variation->fresh()->current_quantity);
    }

    public function test_cashier_can_adjust_stock(): void
    {
        $cashier = User::factory()->cashier()->create();
        $variation = ProductVariation::factory()->create(['current_quantity' => 10]);

        $response = $this->actingAs($cashier)->postJson('/api/stock-movements/adjustment', [
            'product_variation_id' => $variation->id,
            'new_quantity' => 12,
            'reason' => 'Ajuste',
        ]);

        $response->assertCreated();
    }

    public function test_seller_cannot_adjust_stock(): void
    {
        $seller = User::factory()->create();
        $variation = ProductVariation::factory()->create(['current_quantity' => 10]);

        $response = $this->actingAs($seller)->postJson('/api/stock-movements/adjustment', [
            'product_variation_id' => $variation->id,
            'new_quantity' => 12,
            'reason' => 'Ajuste',
        ]);

        $response->assertStatus(403);
    }

    public function test_guest_cannot_adjust_stock(): void
    {
        $variation = ProductVariation::factory()->create();

        $this->postJson('/api/stock-movements/adjustment', [
            'product_variation_id' => $variation->id,
            'new_quantity' => 12,
            'reason' => 'Ajuste',
        ])->assertStatus(401);
    }

    public function test_reason_is_required(): void
    {
        $admin = User::factory()->admin()->create();
        $variation = ProductVariation::factory()->create(['current_quantity' => 10]);

        $response = $this->actingAs($admin)->postJson('/api/stock-movements/adjustment', [
            'product_variation_id' => $variation->id,
            'new_quantity' => 12,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['reason']);
    }

    public function test_new_quantity_cannot_be_negative(): void
    {
        $admin = User::factory()->admin()->create();
        $variation = ProductVariation::factory()->create(['current_quantity' => 10]);

        $response = $this->actingAs($admin)->postJson('/api/stock-movements/adjustment', [
            'product_variation_id' => $variation->id,
            'new_quantity' => -1,
            'reason' => 'Contagem',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['new_quantity']);
        $this->assertEquals(10, $variation->fresh()->current_quantity);
    }

    public function test_product_variation_must_exist(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/stock-movements/adjustment', [
            'product_variation_id' => 999999,
            'new_quantity' => 12,
            'reason' => 'Ajuste',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['product_variation_id']);
    }
}
