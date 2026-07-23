<?php

namespace Tests\Feature\Label;

use App\Models\ProductVariation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabelPrintTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $products): array
    {
        return [
            'products' => $products,
            'page_width' => 210,
            'page_height' => 297,
            'margin_top' => 4,
            'margin_bottom' => 4,
            'margin_left' => 6,
            'margin_right' => 6,
            'columns' => 3,
            'label_width' => 63.5,
            'label_height' => 31,
            'content_fields' => [
                'name' => true,
                'price' => true,
                'code' => true,
                'barcode' => true,
                'store_name' => false,
            ],
            'font_sizes' => [
                'name' => 9,
                'price' => 12,
                'barcode' => 8,
            ],
        ];
    }

    public function test_authenticated_user_can_print_labels(): void
    {
        $user = User::factory()->create();
        $variation = ProductVariation::factory()->create(['sale_price' => 15.90, 'code' => 'PC-001']);

        $response = $this->actingAs($user)->post('/labels/print', $this->payload([
            ['variation_id' => $variation->id, 'quantity' => 3],
        ]));

        $response->assertOk();
        $response->assertSee($variation->product->name);
        $response->assertSee('15,90');
        $response->assertSee('PC-001');
    }

    public function test_label_is_repeated_according_to_requested_quantity(): void
    {
        $user = User::factory()->create();
        $variation = ProductVariation::factory()->create(['sale_price' => 10]);

        $response = $this->actingAs($user)->post('/labels/print', $this->payload([
            ['variation_id' => $variation->id, 'quantity' => 4],
        ]));

        $response->assertOk();
        $this->assertSame(4, substr_count($response->getContent(), 'class="label"'));
    }

    public function test_guest_is_redirected_to_login_instead_of_erroring(): void
    {
        $variation = ProductVariation::factory()->create();

        $response = $this->post('/labels/print', $this->payload([
            ['variation_id' => $variation->id, 'quantity' => 1],
        ]));

        $response->assertRedirect('/login');
    }

    public function test_requires_at_least_one_product(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/labels/print', $this->payload([]));

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['products']);
    }
}
