<?php

namespace App\Actions\Sale;

use App\Actions\Sale\Concerns\BuildsSaleItems;
use App\Enums\CashOperationOrigin;
use App\Enums\CashOperationType;
use App\Enums\CashRegisterStatus;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Exceptions\CashRegisterClosedException;
use App\Models\CashOperation;
use App\Models\CashRegister;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterSaleAction
{
    use BuildsSaleItems;

    public function execute(array $data, User $user): Sale
    {
        return DB::transaction(function () use ($data, $user) {
            $cashRegister = CashRegister::where('status', CashRegisterStatus::Open)->lockForUpdate()->first();

            if (! $cashRegister) {
                throw new CashRegisterClosedException();
            }

            ['subtotal' => $subtotal, 'itemsToInsert' => $itemsToInsert] = $this->buildSaleItems($data['items'], checkStock: true);

            [$saleDiscountType, $saleDiscountValue, $saleDiscountAmount, $total] = $this->resolveSaleDiscount($subtotal, $data);

            $sellerId = $data['seller_id'] ?? $user->id;

            $sale = Sale::create([
                'number' => null,
                'customer_id' => $data['customer_id'] ?? null,
                'seller_id' => $sellerId,
                'cash_register_id' => $cashRegister->id,
                'subtotal' => $subtotal,
                'discount_type' => $saleDiscountType,
                'discount_value' => $saleDiscountValue,
                'discount' => $saleDiscountAmount,
                'total' => $total,
                'payment_method_id' => $data['payment_method_id'],
                'notes' => $data['notes'] ?? null,
                'status' => SaleStatus::Completed,
            ]);
            $sale->update(['number' => 'V'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT)]);

            foreach ($itemsToInsert as $row) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_variation_id' => $row['variation']->id,
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'discount_type' => $row['discount_type'],
                    'discount_value' => $row['discount_value'],
                    'discount' => $row['discount'],
                    'total' => $row['total'],
                    'is_wholesale' => $row['is_wholesale'],
                ]);

                $row['variation']->decrement('current_quantity', $row['quantity']);

                StockMovement::create([
                    'product_variation_id' => $row['variation']->id,
                    'type' => StockMovementType::Sale,
                    'quantity' => $row['quantity'],
                    'origin' => "venda {$sale->number}",
                    'reference_id' => $sale->id,
                    'user_id' => $user->id,
                ]);
            }

            CashOperation::create([
                'cash_register_id' => $cashRegister->id,
                'user_id' => $user->id,
                'type' => CashOperationType::In,
                'origin' => CashOperationOrigin::Sale,
                'reference_id' => $sale->id,
                'payment_method_id' => $data['payment_method_id'],
                'amount' => $total,
                'notes' => "Venda {$sale->number}",
            ]);

            return $sale->load(['items.productVariation.product', 'customer', 'seller', 'paymentMethod', 'cashRegister']);
        }, 3);
    }
}
