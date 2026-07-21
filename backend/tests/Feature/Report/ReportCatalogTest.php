<?php

namespace Tests\Feature\Report;

use App\Enums\SaleStatus;
use App\Models\ProductVariation;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportCatalogTest extends TestCase
{
    use RefreshDatabase;

    public static function catalogKeys(): array
    {
        return [
            ['vendas_totais'],
            ['vendas_produto'],
            ['vendas_categoria'],
            ['vendas_vendedor'],
            ['vendas_forma_pagamento'],
            ['lucro_bruto'],
            ['nivel_estoque'],
            ['valor_estoque'],
        ];
    }

    public function test_guest_cannot_access_catalog(): void
    {
        $this->getJson('/api/reports/catalog/vendas_totais')->assertUnauthorized();
    }

    public function test_unknown_report_key_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/reports/catalog/inexistente')->assertNotFound();
    }

    #[DataProvider('catalogKeys')]
    public function test_catalog_report_returns_title_and_headers(string $key): void
    {
        $user = User::factory()->create();

        Sale::create([
            'seller_id' => User::factory()->create()->id,
            'subtotal' => 100,
            'total' => 100,
            'status' => SaleStatus::Completed,
        ]);
        ProductVariation::factory()->create(['current_quantity' => 1, 'min_quantity' => 5]);

        $response = $this->actingAs($user)->getJson("/api/reports/catalog/{$key}");

        $response->assertOk()
            ->assertJsonPath('data.title', fn ($title) => is_string($title) && $title !== '')
            ->assertJsonStructure(['data' => ['title', 'headers', 'rows']]);
    }

    #[DataProvider('salesByProductCatalogKeys')]
    public function test_sales_by_product_catalog_reports_include_product_code(string $key): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson("/api/reports/catalog/{$key}");

        $response->assertOk();
        $headerKeys = collect($response->json('data.headers'))->pluck('key');
        $this->assertTrue($headerKeys->contains('product_code'));
        $this->assertTrue($headerKeys->contains('legacy_code'));
    }

    public static function salesByProductCatalogKeys(): array
    {
        return [
            ['vendas_produto'],
            ['lucro_bruto'],
        ];
    }

    #[DataProvider('catalogKeys')]
    public function test_catalog_report_exports_pdf(string $key): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get("/api/reports/catalog/{$key}/export/pdf");

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    #[DataProvider('catalogKeys')]
    public function test_catalog_report_exports_excel(string $key): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get("/api/reports/catalog/{$key}/export/excel");

        $response->assertOk();
        $this->assertEquals(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );
    }
}
