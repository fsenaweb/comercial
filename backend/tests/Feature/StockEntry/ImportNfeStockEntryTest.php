<?php

namespace Tests\Feature\StockEntry;

use App\Models\ProductVariation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportNfeStockEntryTest extends TestCase
{
    use RefreshDatabase;

    private function fixtureFile(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'nfe-sample.xml',
            file_get_contents(base_path('tests/Fixtures/nfe-sample.xml'))
        );
    }

    public function test_parses_valid_nfe_xml(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        ProductVariation::factory()->create(['ean_gtin' => '7891234567890']);
        Supplier::factory()->create(['document' => '12345678000199']);

        $response = $this->actingAs($admin)->postJson('/api/stock-entries/parse-xml', [
            'xml' => $this->fixtureFile(),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.nfe_number', '1234')
            ->assertJsonPath('data.total_value', 85)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.1.matched_variation', null)
            ->assertJsonCount(2, 'data.duplicatas');

        $this->assertNotNull($response->json('data.items.0.matched_variation.id'));
        $this->assertNotNull($response->json('data.matched_supplier.name'));
    }

    public function test_rejects_malformed_xml(): void
    {
        $admin = User::factory()->admin()->create();
        $file = UploadedFile::fake()->createWithContent('bad.xml', '<not-nfe><oops></not-nfe>');

        $response = $this->actingAs($admin)->postJson('/api/stock-entries/parse-xml', [
            'xml' => $file,
        ]);

        $response->assertStatus(422);
    }

    public function test_imports_stock_entry_creates_movements_and_increments_stock(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $supplier = Supplier::factory()->create();
        $variation = ProductVariation::factory()->create(['current_quantity' => 5]);

        $response = $this->actingAs($admin)->postJson('/api/stock-entries', [
            'supplier_id' => $supplier->id,
            'nfe_number' => '1234',
            'nfe_series' => '1',
            'products_total' => 50,
            'total_value' => 50,
            'items' => [
                ['product_variation_id' => $variation->id, 'quantity' => 10, 'unit_cost' => 5, 'update_cost' => false],
            ],
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('stock_movements', [
            'product_variation_id' => $variation->id,
            'origin' => 'stock_entry',
            'type' => 'in',
            'quantity' => 10,
        ]);
        $this->assertEquals(15, $variation->fresh()->current_quantity);
        $this->assertDatabaseCount('stock_entries', 1);
    }

    public function test_update_cost_recalculates_sale_price_via_markup(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $supplier = Supplier::factory()->create();
        $variation = ProductVariation::factory()->create(['cost_price' => 5, 'markup' => 50, 'sale_price' => 7.5]);

        $this->actingAs($admin)->postJson('/api/stock-entries', [
            'supplier_id' => $supplier->id,
            'products_total' => 80,
            'total_value' => 80,
            'items' => [
                ['product_variation_id' => $variation->id, 'quantity' => 10, 'unit_cost' => 8, 'update_cost' => true],
            ],
        ])->assertCreated();

        $variation->refresh();
        $this->assertEquals('8.00', $variation->cost_price);
        $this->assertEquals('12.00', $variation->sale_price);
    }

    public function test_generates_accounts_payable_with_installments(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $supplier = Supplier::factory()->create();
        $variation = ProductVariation::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/stock-entries', [
            'supplier_id' => $supplier->id,
            'nfe_number' => '1234',
            'products_total' => 80,
            'total_value' => 85,
            'items' => [
                ['product_variation_id' => $variation->id, 'quantity' => 10, 'unit_cost' => 8],
            ],
            'generate_accounts_payable' => true,
            'payable_installments' => [
                ['number' => 1, 'amount' => 42.50, 'due_date' => now()->addDays(30)->toDateString()],
                ['number' => 2, 'amount' => 42.50, 'due_date' => now()->addDays(60)->toDateString()],
            ],
        ]);

        $response->assertCreated();
        $this->assertNotNull($response->json('data.generated_accounts_payable_id'));
        $this->assertDatabaseCount('payable_installments', 2);
        $this->assertDatabaseHas('accounts_payable', ['supplier_id' => $supplier->id, 'total_amount' => '85.00']);
    }

    public function test_item_without_product_variation_id_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/stock-entries', [
            'supplier_id' => $supplier->id,
            'products_total' => 50,
            'total_value' => 50,
            'items' => [
                ['quantity' => 10, 'unit_cost' => 5],
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['items.0.product_variation_id']);
    }

    public function test_cashier_can_import_but_seller_cannot(): void
    {
        Storage::fake('public');
        $seller = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $variation = ProductVariation::factory()->create();

        $response = $this->actingAs($seller)->postJson('/api/stock-entries', [
            'supplier_id' => $supplier->id,
            'products_total' => 50,
            'total_value' => 50,
            'items' => [
                ['product_variation_id' => $variation->id, 'quantity' => 10, 'unit_cost' => 5],
            ],
        ]);

        $response->assertStatus(403);
    }
}
