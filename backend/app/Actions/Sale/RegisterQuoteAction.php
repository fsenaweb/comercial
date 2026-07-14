<?php

namespace App\Actions\Sale;

use App\Actions\Sale\Concerns\BuildsSaleItems;
use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterQuoteAction
{
    use BuildsSaleItems;

    public function execute(array $data, User $user): Sale
    {
        return DB::transaction(function () use ($data, $user) {
            ['subtotal' => $subtotal, 'itemsToInsert' => $itemsToInsert] = $this->buildSaleItems($data['items'], checkStock: false);

            [$saleDiscountType, $saleDiscountValue, $saleDiscountAmount, $total] = $this->resolveSaleDiscount($subtotal, $data);

            $sellerId = $data['seller_id'] ?? $user->id;

            $sale = Sale::create([
                'number' => null,
                'customer_id' => $data['customer_id'] ?? null,
                'seller_id' => $sellerId,
                'cash_register_id' => null,
                'subtotal' => $subtotal,
                'discount_type' => $saleDiscountType,
                'discount_value' => $saleDiscountValue,
                'discount' => $saleDiscountAmount,
                'total' => $total,
                'payment_method_id' => null,
                'notes' => $data['notes'] ?? null,
                'status' => SaleStatus::Pending,
                'expires_at' => $data['expires_at'] ?? null,
            ]);
            $sale->update(['number' => 'O'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT)]);

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
            }

            return $sale->load(['items.productVariation.product', 'customer', 'seller']);
        }, 3);
    }
}
