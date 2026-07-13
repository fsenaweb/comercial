<?php

namespace Tests\Feature\Stock;

use App\Models\ProductVariation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_register_a_stock_entry(): void
    {
        $admin = User::factory()->admin()->create();
        $variation = ProductVariation::factory()->create(['current_quantity' => 10]);

        $response = $this->actingAs($admin)->postJson('/api/stock-movements/entries', [
            'product_variation_id' => $variation->id,
            'quantity' => 25,
            'origin' => 'Compra NF 1234 - Fornecedor XPTO',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'in')
            ->assertJsonPath('data.quantity', 25)
            ->assertJsonPath('data.origin', 'Compra NF 1234 - Fornecedor XPTO');

        $this->assertEquals(35, $variation->fresh()->current_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_variation_id' => $variation->id,
            'type' => 'in',
            'quantity' => 25,
        ]);
    }

    public function test_cashier_can_register_a_stock_entry(): void
    {
        $cashier = User::factory()->cashier()->create();
        $variation = ProductVariation::factory()->create(['current_quantity' => 10]);

        $response = $this->actingAs($cashier)->postJson('/api/stock-movements/entries', [
            'product_variation_id' => $variation->id,
            'quantity' => 5,
            'origin' => 'Reposição',
        ]);

        $response->assertCreated();
    }

    public function test_seller_cannot_register_a_stock_entry(): void
    {
        $seller = User::factory()->create();
        $variation = ProductVariation::factory()->create();

        $response = $this->actingAs($seller)->postJson('/api/stock-movements/entries', [
            'product_variation_id' => $variation->id,
            'quantity' => 5,
            'origin' => 'Reposição',
        ]);

        $response->assertStatus(403);
    }

    public function test_guest_cannot_register_a_stock_entry(): void
    {
        $variation = ProductVariation::factory()->create();

        $this->postJson('/api/stock-movements/entries', [
            'product_variation_id' => $variation->id,
            'quantity' => 5,
            'origin' => 'Reposição',
        ])->assertStatus(401);
    }

    public function test_quantity_must_be_positive(): void
    {
        $admin = User::factory()->admin()->create();
        $variation = ProductVariation::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/stock-movements/entries', [
            'product_variation_id' => $variation->id,
            'quantity' => 0,
            'origin' => 'Reposição',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['quantity']);
    }

    public function test_origin_is_required(): void
    {
        $admin = User::factory()->admin()->create();
        $variation = ProductVariation::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/stock-movements/entries', [
            'product_variation_id' => $variation->id,
            'quantity' => 5,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['origin']);
    }
}
