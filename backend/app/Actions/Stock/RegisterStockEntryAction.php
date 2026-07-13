<?php

namespace App\Actions\Stock;

use App\Enums\StockMovementType;
use App\Models\ProductVariation;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterStockEntryAction
{
    public function execute(array $data, User $user): StockMovement
    {
        return DB::transaction(function () use ($data, $user) {
            $variation = ProductVariation::whereKey($data['product_variation_id'])->lockForUpdate()->firstOrFail();

            $variation->increment('current_quantity', $data['quantity']);

            return StockMovement::create([
                'product_variation_id' => $variation->id,
                'type' => StockMovementType::In,
                'quantity' => $data['quantity'],
                'origin' => $data['origin'],
                'user_id' => $user->id,
            ]);
        });
    }
}
