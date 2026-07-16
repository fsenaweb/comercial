<?php

namespace Tests\Feature\Report;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_print(): void
    {
        $this->getJson('/api/reports/catalog/vendas_totais/print')->assertUnauthorized();
    }

    public function test_unknown_report_key_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/reports/catalog/inexistente/print')->assertNotFound();
    }

    public function test_print_returns_browser_friendly_html_with_letterhead(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/api/reports/catalog/vendas_totais/print');

        $response->assertOk();
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
        $this->assertNull($response->headers->get('Content-Disposition'));
        $response->assertSee('window.print()', false);
    }
}
