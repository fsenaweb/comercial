<?php

namespace Tests\Feature\Product;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /product-variations/lookup|search — substituem o carregamento do
 * catálogo inteiro no navegador (PDV/Etiquetas) por busca indexada no banco.
 * Ver docs/11-migracao-sistema-legado.md, achado de escala com 13 mil produtos.
 */
class ProductVariationSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_lookup_or_search(): void
    {
        $this->getJson('/api/product-variations/lookup?code=X')->assertStatus(401);
        $this->getJson('/api/product-variations/search?q=X')->assertStatus(401);
    }

    public function test_lookup_finds_exact_match_by_product_code(): void
    {
        $user = User::factory()->create();
        $variation = ProductVariation::factory()->create(['code' => 'PRF-M8']);

        $response = $this->actingAs($user)->getJson('/api/product-variations/lookup?code=PRF-M8');

        $response->assertOk()->assertJsonPath('data.id', $variation->id);
    }

    public function test_lookup_finds_exact_match_by_ean_gtin(): void
    {
        $user = User::factory()->create();
        $variation = ProductVariation::factory()->create(['ean_gtin' => '7891234567890']);

        $response = $this->actingAs($user)->getJson('/api/product-variations/lookup?code=7891234567890');

        $response->assertOk()->assertJsonPath('data.id', $variation->id);
    }

    public function test_lookup_falls_back_to_reference_when_no_code_or_ean_matches(): void
    {
        $user = User::factory()->create();
        $variation = ProductVariation::factory()->create(['reference' => 'REF-LIVRE']);

        $response = $this->actingAs($user)->getJson('/api/product-variations/lookup?code=REF-LIVRE');

        $response->assertOk()->assertJsonPath('data.id', $variation->id);
    }

    public function test_lookup_returns_404_when_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/product-variations/lookup?code=INEXISTENTE')->assertStatus(404);
    }

    public function test_lookup_ignores_inactive_products(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['active' => false]);
        ProductVariation::factory()->create(['product_id' => $product->id, 'code' => 'INATIVO-01']);

        $this->actingAs($user)->getJson('/api/product-variations/lookup?code=INATIVO-01')->assertStatus(404);
    }

    public function test_search_matches_by_product_name(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Parafuso Sextavado M8']);
        ProductVariation::factory()->create(['product_id' => $product->id]);
        $other = Product::factory()->create(['name' => 'Arruela']);
        ProductVariation::factory()->create(['product_id' => $other->id]);

        $response = $this->actingAs($user)->getJson('/api/product-variations/search?q=Sextavado');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals('Parafuso Sextavado M8', $response->json('data.0.product_name'));
    }

    public function test_search_includes_max_quantity_and_markup(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Parafuso Com Limite']);
        ProductVariation::factory()->create(['product_id' => $product->id, 'max_quantity' => 50, 'markup' => 30]);

        $response = $this->actingAs($user)->getJson('/api/product-variations/search?q=Limite');

        $response->assertOk()
            ->assertJsonPath('data.0.max_quantity', 50)
            ->assertJsonPath('data.0.markup', '30.00');
    }

    public function test_search_matches_multiple_words_regardless_of_order_or_adjacency(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Parafuso Sextavado M8 X100']);
        ProductVariation::factory()->create(['product_id' => $product->id]);
        $other = Product::factory()->create(['name' => 'Parafuso Allen M8 X50']);
        ProductVariation::factory()->create(['product_id' => $other->id]);

        // "paraf" e "x100" não são contíguos no nome ("Sextavado M8" fica no
        // meio) — cada palavra precisa bater em qualquer lugar, não a frase
        // inteira em sequência.
        $response = $this->actingAs($user)->getJson('/api/product-variations/search?q=paraf x100');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals('Parafuso Sextavado M8 X100', $response->json('data.0.product_name'));
    }

    public function test_search_word_order_does_not_matter(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Filtro de Ar Condicionado']);
        ProductVariation::factory()->create(['product_id' => $product->id]);

        $response = $this->actingAs($user)->getJson('/api/product-variations/search?q=condicionado filtro');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_search_requires_all_words_to_match(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Parafuso Sextavado M8']);
        ProductVariation::factory()->create(['product_id' => $product->id]);

        $response = $this->actingAs($user)->getJson('/api/product-variations/search?q=parafuso inexistente');

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_search_respects_limit(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Parafuso Modelo A']);
        ProductVariation::factory()->count(5)->create(['product_id' => $product->id]);

        $response = $this->actingAs($user)->getJson('/api/product-variations/search?q=Parafuso&limit=2');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_search_excludes_inactive_products(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Produto Inativo Teste', 'active' => false]);
        ProductVariation::factory()->create(['product_id' => $product->id]);

        $response = $this->actingAs($user)->getJson('/api/product-variations/search?q=Inativo');

        $response->assertOk()->assertJsonCount(0, 'data');
    }
}
