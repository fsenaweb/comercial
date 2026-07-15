<?php

namespace App\Actions\Sale;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConvertQuoteToSaleAction
{
    public function __construct(private RegisterSaleAction $registerSaleAction)
    {
    }

    public function execute(Sale $quote, array $data, User $user): Sale
    {
        return DB::transaction(function () use ($quote, $data, $user) {
            $quote = Sale::where('id', $quote->id)->lockForUpdate()->firstOrFail();

            if ($quote->status !== SaleStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => 'Este orçamento não está mais pendente (já foi convertido ou cancelado).',
                ]);
            }

            if ($quote->expires_at !== null && $quote->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'expires_at' => 'Este orçamento está vencido e não pode mais ser convertido.',
                ]);
            }

            $items = $quote->items()->get();

            $sale = $this->registerSaleAction->execute([
                'customer_id' => $quote->customer_id,
                'seller_id' => $quote->seller_id,
                'payments' => $data['payments'],
                'discount_type' => $quote->discount_type->value,
                'discount_value' => (string) $quote->discount_value,
                'notes' => $quote->notes,
                'items' => $items->map(fn ($item) => [
                    'product_variation_id' => $item->product_variation_id,
                    'quantity' => $item->quantity,
                    'apply_wholesale' => $item->is_wholesale,
                    'discount_type' => $item->discount_type->value,
                    'discount_value' => (string) $item->discount_value,
                ])->all(),
            ], $user);

            $quote->update([
                'status' => SaleStatus::Converted,
                'converted_to_sale_id' => $sale->id,
                'converted_at' => now(),
            ]);

            return $sale;
        }, 3);
    }
}
