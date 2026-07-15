<?php

namespace App\Actions\Sale\Concerns;

use App\Actions\Concerns\ResolvesDiscounts;
use App\Enums\DiscountType;
use App\Models\ProductVariation;

trait BuildsSaleItems
{
    use ResolvesDiscounts;

    /**
     * Trava (quando aplicável) as variações envolvidas e monta o preço/desconto/atacado
     * de cada item — mesma lógica usada tanto para registrar uma venda quanto um orçamento.
     * `$checkStock` fica desligado no orçamento (não há garantia de estoque na criação,
     * só na conversão em venda de verdade).
     */
    private function buildSaleItems(array $items, bool $checkStock): array
    {
        $variationIds = collect($items)->pluck('product_variation_id')->unique()->sort()->values();
        $variations = ProductVariation::whereIn('id', $variationIds)->orderBy('id')->with('product')->lockForUpdate()->get()->keyBy('id');

        if ($variations->count() !== $variationIds->count()) {
            abort(404, 'Um ou mais produtos da venda não foram encontrados.');
        }

        $subtotal = '0.00';
        $itemsToInsert = [];

        foreach ($items as $item) {
            $variation = $variations[$item['product_variation_id']];
            if ($checkStock && $variation->current_quantity < $item['quantity']) {
                abort(422, "Estoque insuficiente para {$variation->product->name} (disponível: {$variation->current_quantity}).");
            }
            $isWholesale = ($item['apply_wholesale'] ?? false)
                && $variation->wholesale_min_qty !== null
                && $variation->wholesale_price !== null
                && $item['quantity'] >= $variation->wholesale_min_qty;
            $unitPrice = (string) ($isWholesale ? $variation->wholesale_price : $variation->sale_price);
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
                'is_wholesale' => $isWholesale,
            ];
        }

        return ['subtotal' => $subtotal, 'itemsToInsert' => $itemsToInsert];
    }

    private function resolveSaleDiscount(string $subtotal, array $data): array
    {
        $saleDiscountType = DiscountType::from($data['discount_type'] ?? DiscountType::Fixed->value);
        $saleDiscountValue = (string) ($data['discount_value'] ?? 0);
        $saleDiscountAmount = $this->resolveDiscountAmount($subtotal, $saleDiscountType, $saleDiscountValue);
        $total = bcsub($subtotal, $saleDiscountAmount, 2);
        if (bccomp($total, '0', 2) < 0) {
            $total = '0.00';
        }

        return [$saleDiscountType, $saleDiscountValue, $saleDiscountAmount, $total];
    }
}
