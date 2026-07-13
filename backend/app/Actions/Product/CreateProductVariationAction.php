<?php

namespace App\Actions\Product;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateProductVariationAction
{
    public function execute(Product $product, array $data, User $user): ProductVariation
    {
        return DB::transaction(function () use ($product, $data, $user) {
            $variation = $product->variations()->create([
                'color' => $data['color'] ?? null,
                'size' => $data['size'] ?? null,
                'ean_gtin' => $data['ean_gtin'] ?? null,
                'product_code' => $data['product_code'],
                'cost_price' => $data['cost_price'],
                'markup' => $data['markup'] ?? null,
                'sale_price' => $data['sale_price'],
                'current_quantity' => $data['initial_quantity'],
                'min_quantity' => $data['min_quantity'] ?? null,
                'max_quantity' => $data['max_quantity'] ?? null,
                'wholesale_min_qty' => $data['wholesale_min_qty'] ?? null,
                'wholesale_price' => $data['wholesale_price'] ?? null,
            ]);

            StockMovement::create([
                'product_variation_id' => $variation->id,
                'type' => StockMovementType::Adjustment,
                'quantity' => $data['initial_quantity'],
                'origin' => 'estoque inicial',
                'user_id' => $user->id,
            ]);

            return $variation;
        });
    }
}
