<?php

namespace Tests\Feature\LegacyImport;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class LegacyImportTest extends TestCase
{
    use RefreshDatabase;

    private string $fixturesPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturesPath = base_path('tests/Fixtures/legacy-import');
    }

    public function test_fails_without_admin_user(): void
    {
        $exitCode = Artisan::call('legacy:import', ['path' => $this->fixturesPath]);

        $this->assertSame(1, $exitCode);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_imports_categories_brands_and_units(): void
    {
        User::factory()->admin()->create();

        Artisan::call('legacy:import', ['path' => $this->fixturesPath]);

        $this->assertDatabaseHas('categories', ['name' => 'DIVERSOS']);
        $this->assertDatabaseHas('categories', ['name' => 'FERRAGENS']);
        $this->assertDatabaseHas('brands', ['name' => 'TRAMONTINA']);
        $this->assertDatabaseHas('units', ['abbreviation' => 'UN', 'name' => 'UNIDADE']);
    }

    public function test_imports_products_with_correct_mapping(): void
    {
        User::factory()->admin()->create();

        Artisan::call('legacy:import', ['path' => $this->fixturesPath]);

        $variation = ProductVariation::where('code', '1')->firstOrFail();
        $product = $variation->product;

        $this->assertSame('Parafuso Sextavado M8', $product->name);
        $this->assertTrue($product->active);
        $this->assertSame('7891234567890', $variation->ean_gtin);
        $this->assertSame('2.25', $variation->sale_price);
        $this->assertSame(100, $variation->current_quantity);
        $this->assertSame('2.00', $variation->wholesale_price);
        $this->assertSame(12, $variation->wholesale_min_qty);
        $this->assertNotNull($product->brand_id);
        // CODIGO legado (único no sistema de origem) vira `code`, o
        // identificador único do sistema; REFERENCIA vira `reference`, campo
        // livre de classificação — pedido do cliente, 2026-07-22, ver
        // docs/11-migracao-sistema-legado.md.
        $this->assertSame('PRF-M8', $variation->reference);
        $this->assertSame('C01 BA05', $product->location);
    }

    public function test_imports_product_without_location_as_null(): void
    {
        User::factory()->admin()->create();

        Artisan::call('legacy:import', ['path' => $this->fixturesPath]);

        $product = ProductVariation::where('code', '2')->firstOrFail()->product;

        $this->assertNull($product->location);
    }

    public function test_normalizes_sem_gtin_barcode_to_null(): void
    {
        User::factory()->admin()->create();

        Artisan::call('legacy:import', ['path' => $this->fixturesPath]);

        $variation = ProductVariation::where('code', '2')->firstOrFail();

        $this->assertNull($variation->ean_gtin);
        $this->assertFalse($variation->product->active);
    }

    public function test_reference_can_repeat_across_products(): void
    {
        User::factory()->admin()->create();

        Artisan::call('legacy:import', ['path' => $this->fixturesPath]);

        // REFERENCIA "DUP" tinha ~387 colisões no legado — não precisa mais
        // ser única, então os dois produtos ficam com a mesma referência,
        // cada um com seu próprio `code` (CODIGO), que é o campo único.
        $this->assertDatabaseHas('product_variations', ['code' => '3', 'reference' => 'DUP']);
        $this->assertDatabaseHas('product_variations', ['code' => '4', 'reference' => 'DUP']);
    }

    public function test_ignores_restaurant_placeholder_products(): void
    {
        User::factory()->admin()->create();

        Artisan::call('legacy:import', ['path' => $this->fixturesPath]);

        $this->assertDatabaseMissing('products', ['name' => 'TAXA DE COUVERT']);
    }

    public function test_normalizes_out_of_range_markup_to_null(): void
    {
        User::factory()->admin()->create();

        // Achado real na importação completa do dump: 148 dos 13.280 produtos
        // têm MARGEM absurda no legado (erro de digitação antigo), estourando
        // o limite de product_variations.markup (decimal(7,2)) e derrubando a
        // transação inteira — ver docs/11-migracao-sistema-legado.md.
        Artisan::call('legacy:import', ['path' => $this->fixturesPath]);

        $variation = ProductVariation::where('code', '6')->firstOrFail();

        $this->assertNull($variation->markup);
    }

    public function test_imports_service_type_product(): void
    {
        User::factory()->admin()->create();

        Artisan::call('legacy:import', ['path' => $this->fixturesPath]);

        $product = ProductVariation::where('code', '5')->firstOrFail()->product;

        $this->assertSame('service', $product->type->value);
    }

    public function test_creates_initial_stock_movement_per_product(): void
    {
        User::factory()->admin()->create();

        Artisan::call('legacy:import', ['path' => $this->fixturesPath]);

        $variation = ProductVariation::where('code', '1')->firstOrFail();

        $this->assertDatabaseHas('stock_movements', [
            'product_variation_id' => $variation->id,
            'type' => 'adjustment',
            'quantity' => 100,
            'origin' => 'estoque inicial (importação sistema legado)',
        ]);
    }

    public function test_imports_customers_with_nullable_phone_and_company_flag(): void
    {
        User::factory()->admin()->create();

        Artisan::call('legacy:import', ['path' => $this->fixturesPath]);

        $consumidor = Customer::where('name', 'CONSUMIDOR FINAL')->firstOrFail();
        $this->assertNull($consumidor->mobile_phone);
        $this->assertFalse($consumidor->is_company);

        $empresa = Customer::where('document', '47.643.797/0001-09')->firstOrFail();
        $this->assertSame('GRAFICA TESTE', $empresa->name);
        $this->assertSame('88999998888', $empresa->mobile_phone);
        $this->assertTrue($empresa->is_company);
    }

    public function test_running_twice_does_not_duplicate_records(): void
    {
        User::factory()->admin()->create();

        Artisan::call('legacy:import', ['path' => $this->fixturesPath]);
        $productsAfterFirstRun = Product::count();
        $customersAfterFirstRun = Customer::count();
        $movementsAfterFirstRun = StockMovement::count();
        $categoriesAfterFirstRun = Category::count();

        Artisan::call('legacy:import', ['path' => $this->fixturesPath]);

        $this->assertSame($productsAfterFirstRun, Product::count());
        $this->assertSame($customersAfterFirstRun, Customer::count());
        $this->assertSame($categoriesAfterFirstRun, Category::count());
        // Estoque não mudou entre as duas rodadas — nenhum ajuste novo deve ter sido criado.
        $this->assertSame($movementsAfterFirstRun, StockMovement::count());
    }
}
