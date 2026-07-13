<?php

namespace App\Actions\Sale;

use App\Enums\CashOperationOrigin;
use App\Enums\CashOperationType;
use App\Enums\CashRegisterStatus;
use App\Enums\DiscountType;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Exceptions\CashRegisterClosedException;
use App\Models\CashOperation;
use App\Models\CashRegister;
use App\Models\ProductVariation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterSaleAction
{
    public function execute(array $data, User $user): Sale
    {
        return DB::transaction(function () use ($data, $user) {
            $cashRegister = CashRegister::where('status', CashRegisterStatus::Open)->lockForUpdate()->first();

            if (! $cashRegister) {
                throw new CashRegisterClosedException();
            }

            $variationIds = collect($data['items'])->pluck('product_variation_id')->unique()->sort()->values();
            $variations = ProductVariation::whereIn('id', $variationIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            if ($variations->count() !== $variationIds->count()) {
                abort(404, 'Um ou mais produtos da venda não foram encontrados.');
            }

            $subtotal = '0.00';
            $itemsToInsert = [];

            foreach ($data['items'] as $item) {
                $variation = $variations[$item['product_variation_id']];
                $unitPrice = (string) $variation->sale_price;
                $lineDiscountType = DiscountType::from($item['discount_type'] ?? DiscountType::Fixed->value);
                $lineDiscountValue = (string) ($item['discount_value'] ?? 0);
                $lineGross = bcmul($unitPrice, (string) $item['quantity'], 2);
                $lineDiscountAmount = $this->resolveDiscountAmount($lineGross, $lineDiscountType, $lineDiscountValue);
                $lineTotal = bcsub($lineGross, $lineDiscountAmount, 2);
                if (bccomp($lineTotal, '0', 2) < 0) {
                    $lineTotal = '0.00';
                }
                $subtotal = bcadd($subtotal, $lineTotal, 2);

                $itemsToInsert[] = [
                    'variation' => $variation,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'discount_type' => $lineDiscountType,
                    'discount_value' => $lineDiscountValue,
                    'discount' => $lineDiscountAmount,
                    'total' => $lineTotal,
                ];
            }

            $saleDiscountType = DiscountType::from($data['discount_type'] ?? DiscountType::Fixed->value);
            $saleDiscountValue = (string) ($data['discount_value'] ?? 0);
            $saleDiscountAmount = $this->resolveDiscountAmount($subtotal, $saleDiscountType, $saleDiscountValue);
            $total = bcsub($subtotal, $saleDiscountAmount, 2);
            if (bccomp($total, '0', 2) < 0) {
                $total = '0.00';
            }

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

    /**
     * Resolve o valor absoluto do desconto a partir do tipo escolhido pelo
     * operador: fixo usa o valor direto, percentual multiplica e só arredonda
     * pra 2 casas no final (evita erro de arredondamento intermediário).
     */
    private function resolveDiscountAmount(string $base, DiscountType $type, string $value): string
    {
        if ($type === DiscountType::Percentage) {
            return bcdiv(bcmul($base, $value, 4), '100', 2);
        }

        return bcadd($value, '0', 2);
    }
}
