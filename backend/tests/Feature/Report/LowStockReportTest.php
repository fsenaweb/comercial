<?php

namespace Tests\Feature\Report;

use App\Models\ProductVariation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LowStockReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access(): void
    {
        $this->getJson('/api/reports/low-stock')->assertUnauthorized();
    }

    public function test_lists_only_variations_strictly_below_minimum(): void
    {
        $user = User::factory()->create();

        $below = ProductVariation::factory()->create(['current_quantity' => 2, 'min_quantity' => 5]);
        ProductVariation::factory()->create(['current_quantity' => 5, 'min_quantity' => 5]);
        ProductVariation::factory()->create(['current_quantity' => 0, 'min_quantity' => null]);
        ProductVariation::factory()->create(['current_quantity' => 20, 'min_quantity' => 5]);

        $response = $this->actingAs($user)->getJson('/api/reports/low-stock');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains($below->id));
    }
}
