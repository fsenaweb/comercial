<?php

namespace Tests\Feature\Sale;

use App\Actions\Sale\RegisterSaleAction;
use App\Models\CashRegister;
use App\Models\PaymentMethod;
use App\Models\ProductVariation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_the_receipt(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 20]);

        $sale = app(RegisterSaleAction::class)->execute([
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 20]],
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 2]],
        ], $admin);

        $response = $this->actingAs($admin)->get("/sales/{$sale->id}/receipt");

        $response->assertOk();
        $response->assertSee('DOCUMENTO NÃO FISCAL');
        $response->assertSee($sale->number);
        $response->assertSee($variation->product->name);
        $response->assertSee($paymentMethod->name);
    }

    public function test_receipt_lists_every_payment_leg_of_a_split_payment_sale(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        $pix = PaymentMethod::factory()->create(['name' => 'Pix', 'active_on_pos' => true]);
        $cash = PaymentMethod::factory()->create(['name' => 'Dinheiro', 'active_on_pos' => true]);
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 20]);

        $sale = app(RegisterSaleAction::class)->execute([
            'payments' => [
                ['payment_method_id' => $pix->id, 'amount' => 12],
                ['payment_method_id' => $cash->id, 'amount' => 8],
            ],
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 2]],
        ], $admin);

        $response = $this->actingAs($admin)->get("/sales/{$sale->id}/receipt");

        $response->assertOk();
        $response->assertSee('Pix');
        $response->assertSee('Dinheiro');
    }

    public function test_guest_is_redirected_to_login_instead_of_erroring(): void
    {
        CashRegister::factory()->open()->create();
        $admin = User::factory()->admin()->create();
        $paymentMethod = PaymentMethod::factory()->create(['active_on_pos' => true]);
        $variation = ProductVariation::factory()->create(['sale_price' => 10, 'current_quantity' => 20]);

        $sale = app(RegisterSaleAction::class)->execute([
            'payments' => [['payment_method_id' => $paymentMethod->id, 'amount' => 10]],
            'items' => [['product_variation_id' => $variation->id, 'quantity' => 1]],
        ], $admin);

        $response = $this->get("/sales/{$sale->id}/receipt");

        $response->assertRedirect('/login');
    }
}
