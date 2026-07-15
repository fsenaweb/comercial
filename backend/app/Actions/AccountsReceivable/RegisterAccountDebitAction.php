<?php

namespace App\Actions\AccountsReceivable;

use App\Actions\Concerns\ResolvesDiscounts;
use App\Enums\AccountEntryType;
use App\Enums\DiscountType;
use App\Enums\StockMovementType;
use App\Models\AccountEntry;
use App\Models\AccountEntryItem;
use App\Models\AccountsReceivable;
use App\Models\ProductVariation;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterAccountDebitAction
{
    use ResolvesDiscounts;

    /**
     * Lança uma compra itemizada na conta corrente do cliente ("caderneta")
     * — abre a conta automaticamente no primeiro débito. Itens vêm do
     * catálogo real (mesma trava/ordenação por id do RegisterSaleAction,
     * evita deadlock entre lançamentos concorrentes) e **decrementam
     * estoque como uma venda decrementaria** — a mercadoria realmente saiu
     * da loja, só o pagamento é que fica em aberto. Não toca caixa (nenhum
     * dinheiro entrou ainda).
     */
    public function execute(array $data, User $user): AccountEntry
    {
        return DB::transaction(function () use ($data, $user) {
            $variationIds = collect($data['items'])->pluck('product_variation_id')->unique()->sort()->values();
            $variations = ProductVariation::whereIn('id', $variationIds)->orderBy('id')->with('product')->lockForUpdate()->get()->keyBy('id');

            if ($variations->count() !== $variationIds->count()) {
                abort(404, 'Um ou mais produtos da compra não foram encontrados.');
            }

            $subtotal = '0.00';
            $itemsToInsert = [];

            foreach ($data['items'] as $item) {
                $variation = $variations[$item['product_variation_id']];

                if ($variation->current_quantity < $item['quantity']) {
                    abort(422, "Estoque insuficiente para {$variation->product->name} (disponível: {$variation->current_quantity}).");
                }

                $unitPrice = (string) ($item['unit_price'] ?? $variation->sale_price);
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

            $entryDiscountType = DiscountType::from($data['discount_type'] ?? DiscountType::Fixed->value);
            $entryDiscountValue = (string) ($data['discount_value'] ?? 0);
            $entryDiscountAmount = $this->resolveDiscountAmount($subtotal, $entryDiscountType, $entryDiscountValue);
            $total = bcsub($subtotal, $entryDiscountAmount, 2);
            if (bccomp($total, '0', 2) < 0) {
                $total = '0.00';
            }

            $account = AccountsReceivable::firstOrCreate(
                ['customer_id' => $data['customer_id']],
                ['created_by' => $user->id]
            );

            $entry = AccountEntry::create([
                'accounts_receivable_id' => $account->id,
                'type' => AccountEntryType::Purchase,
                'subtotal' => $subtotal,
                'discount_type' => $entryDiscountType,
                'discount_value' => $entryDiscountValue,
                'discount' => $entryDiscountAmount,
                'amount' => $total,
                'description' => $data['description'],
                'created_by' => $user->id,
            ]);

            foreach ($itemsToInsert as $row) {
                AccountEntryItem::create([
                    'account_entry_id' => $entry->id,
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
                    'type' => StockMovementType::Out,
                    'quantity' => $row['quantity'],
                    'origin' => "crediário — {$account->customer?->name}",
                    'reference_id' => $entry->id,
                    'user_id' => $user->id,
                ]);
            }

            return $entry->load('items.productVariation.product', 'accountsReceivable.customer');
        });
    }
}
