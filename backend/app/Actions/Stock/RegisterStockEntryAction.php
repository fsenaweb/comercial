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

            // Preço só é atualizado se quem registrou a entrada for admin
            // (mesma regra de ProductVariationPolicy::update) - o pedido do
            // cliente foi permitir atualizar o preço no mesmo fluxo da
            // entrada pra não repetir o processo em duas telas, mas sem
            // abrir uma via alternativa de alterar preço pra quem não pode.
            // Checado aqui (não só escondido no front) porque quem chama a
            // Action recebe os dados já validados do request, que não tem
            // acesso ao papel de quem enviou.
            if ($user->isAdmin() && isset($data['cost_price'], $data['sale_price'])) {
                $variation->update([
                    'cost_price' => $data['cost_price'],
                    'markup' => $data['markup'] ?? null,
                    'sale_price' => $data['sale_price'],
                ]);
            }

            return StockMovement::create([
                'product_variation_id' => $variation->id,
                'type' => StockMovementType::In,
                'quantity' => $data['quantity'],
                'origin' => $data['origin'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'user_id' => $user->id,
            ]);
        });
    }
}
