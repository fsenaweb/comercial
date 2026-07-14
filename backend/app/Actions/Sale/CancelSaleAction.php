<?php

namespace App\Actions\Sale;

use App\Enums\CashOperationOrigin;
use App\Enums\CashOperationType;
use App\Enums\CashRegisterStatus;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Exceptions\CashRegisterClosedException;
use App\Models\CashOperation;
use App\Models\CashRegister;
use App\Models\ProductVariation;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelSaleAction
{
    public function execute(Sale $sale, string $reason, User $user): Sale
    {
        return DB::transaction(function () use ($sale, $reason, $user) {
            $sale = Sale::where('id', $sale->id)->lockForUpdate()->firstOrFail();

            if ($sale->status === SaleStatus::Pending) {
                $sale->update([
                    'status' => SaleStatus::Canceled,
                    'canceled_reason' => $reason,
                    'canceled_at' => now(),
                    'canceled_by' => $user->id,
                ]);

                return $sale->load(['items.productVariation.product', 'customer', 'seller', 'canceledBy']);
            }

            if ($sale->status !== SaleStatus::Completed) {
                throw ValidationException::withMessages([
                    'status' => 'Esta venda ou orçamento já está cancelado ou convertido, e não pode ser cancelado novamente.',
                ]);
            }

            $cashRegister = CashRegister::where('status', CashRegisterStatus::Open)->lockForUpdate()->first();

            if (! $cashRegister) {
                throw new CashRegisterClosedException();
            }

            $items = $sale->items()->get();
            $variationIds = $items->pluck('product_variation_id')->unique()->sort()->values();
            $variations = ProductVariation::whereIn('id', $variationIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            foreach ($items as $item) {
                $variation = $variations[$item->product_variation_id];
                $variation->increment('current_quantity', $item->quantity);

                StockMovement::create([
                    'product_variation_id' => $variation->id,
                    'type' => StockMovementType::In,
                    'quantity' => $item->quantity,
                    'origin' => "cancelamento venda {$sale->number}",
                    'reference_id' => $sale->id,
                    'user_id' => $user->id,
                ]);
            }

            CashOperation::create([
                'cash_register_id' => $cashRegister->id,
                'user_id' => $user->id,
                'type' => CashOperationType::Out,
                'origin' => CashOperationOrigin::Adjustment,
                'reference_id' => $sale->id,
                'payment_method_id' => $sale->payment_method_id,
                'amount' => $sale->total,
                'notes' => "Cancelamento venda {$sale->number}: {$reason}",
            ]);

            $sale->update([
                'status' => SaleStatus::Canceled,
                'canceled_reason' => $reason,
                'canceled_at' => now(),
                'canceled_by' => $user->id,
            ]);

            return $sale->load(['items.productVariation.product', 'customer', 'seller', 'paymentMethod', 'cashRegister', 'canceledBy']);
        }, 3);
    }
}
