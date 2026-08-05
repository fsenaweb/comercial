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
            ->assertJsonPath('data.quantity', '25.000')
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

    public function test_origin_is_optional(): void
    {
        $admin = User::factory()->admin()->create();
        $variation = ProductVariation::factory()->create(['current_quantity' => 10]);

        $response = $this->actingAs($admin)->postJson('/api/stock-movements/entries', [
            'product_variation_id' => $variation->id,
            'quantity' => 5,
        ]);

        $response->assertCreated()->assertJsonPath('data.origin', null);
        $this->assertDatabaseHas('stock_movements', [
            'product_variation_id' => $variation->id,
            'origin' => null,
        ]);
    }

    public function test_blank_origin_is_stored_as_null(): void
    {
        $admin = User::factory()->admin()->create();
        $variation = ProductVariation::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/stock-movements/entries', [
            'product_variation_id' => $variation->id,
            'quantity' => 5,
            'origin' => '',
        ]);

        $response->assertCreated()->assertJsonPath('data.origin', null);
    }

    public function test_admin_can_register_a_fractional_stock_entry(): void
    {
        $admin = User::factory()->admin()->create();
        $variation = ProductVariation::factory()->create(['current_quantity' => 10]);

        $response = $this->actingAs($admin)->postJson('/api/stock-movements/entries', [
            'product_variation_id' => $variation->id,
            'quantity' => 2.5,
            'origin' => 'Compra fracionada',
        ]);

        $response->assertCreated()->assertJsonPath('data.quantity', '2.500');
        $this->assertSame('12.500', $variation->fresh()->current_quantity);
    }

    public function test_admin_can_update_price_in_the_same_entry(): void
    {
        $admin = User::factory()->admin()->create();
        $variation = ProductVariation::factory()->create([
            'current_quantity' => 10,
            'cost_price' => 1.00,
            'markup' => 20,
            'sale_price' => 1.20,
        ]);

        $response = $this->actingAs($admin)->postJson('/api/stock-movements/entries', [
            'product_variation_id' => $variation->id,
            'quantity' => 5,
            'origin' => 'Compra NF 1234',
            'cost_price' => 1.50,
            'markup' => 30,
            'sale_price' => 1.95,
        ]);

        $response->assertCreated();
        $fresh = $variation->fresh();
        $this->assertSame('1.50', $fresh->cost_price);
        $this->assertSame('30.00', $fresh->markup);
        $this->assertSame('1.95', $fresh->sale_price);
    }

    public function test_cashier_sending_price_fields_does_not_change_price(): void
    {
        $cashier = User::factory()->cashier()->create();
        $variation = ProductVariation::factory()->create([
            'current_quantity' => 10,
            'cost_price' => 1.00,
            'sale_price' => 1.20,
        ]);

        $response = $this->actingAs($cashier)->postJson('/api/stock-movements/entries', [
            'product_variation_id' => $variation->id,
            'quantity' => 5,
            'cost_price' => 999,
            'sale_price' => 999,
        ]);

        $response->assertCreated();
        $fresh = $variation->fresh();
        $this->assertSame('1.00', $fresh->cost_price);
        $this->assertSame('1.20', $fresh->sale_price);
    }

    public function test_price_fields_are_optional(): void
    {
        $admin = User::factory()->admin()->create();
        $variation = ProductVariation::factory()->create([
            'current_quantity' => 10,
            'cost_price' => 1.00,
            'sale_price' => 1.20,
        ]);

        $response = $this->actingAs($admin)->postJson('/api/stock-movements/entries', [
            'product_variation_id' => $variation->id,
            'quantity' => 5,
        ]);

        $response->assertCreated();
        $fresh = $variation->fresh();
        $this->assertSame('1.00', $fresh->cost_price);
        $this->assertSame('1.20', $fresh->sale_price);
    }
}
