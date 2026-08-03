<?php

namespace App\Actions\Stock;

use App\Enums\StockMovementType;
use App\Models\ProductVariation;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdjustStockAction
{
    public function execute(array $data, User $user): StockMovement
    {
        return DB::transaction(function () use ($data, $user) {
            $variation = ProductVariation::whereKey($data['product_variation_id'])->lockForUpdate()->firstOrFail();

            $delta = bcsub((string) $data['new_quantity'], (string) $variation->current_quantity, 3);

            $variation->update(['current_quantity' => $data['new_quantity']]);

            return StockMovement::create([
                'product_variation_id' => $variation->id,
                'type' => StockMovementType::Adjustment,
                'quantity' => $delta,
                'origin' => $data['reason'],
                'user_id' => $user->id,
            ]);
        });
    }
}
