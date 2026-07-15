<?php

namespace App\Actions\AccountsReceivable;

use App\Actions\Concerns\ResolvesDiscounts;
use App\Enums\DiscountType;
use App\Models\AccountEntry;
use App\Models\AccountsReceivable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateAccountDebitAction
{
    use ResolvesDiscounts;

    /**
     * Revisa preço/desconto de uma compra já lançada — usado no momento do
     * pagamento, quando o cliente e o operador acertam o valor final
     * (desconto combinado, correção de preço etc.). Produto, quantidade e
     * estoque **não mudam aqui** — a mercadoria já saiu da loja, só o valor
     * devido é que pode ser revisado. `lockForUpdate()` na conta garante
     * que o saldo recalculado depois da revisão não fica negativo (ou
     * seja, não dá pra reduzir uma compra abaixo do que o cliente já
     * pagou).
     */
    public function execute(AccountEntry $entry, array $data): AccountEntry
    {
        return DB::transaction(function () use ($entry, $data) {
            $account = AccountsReceivable::whereKey($entry->accounts_receivable_id)->lockForUpdate()->firstOrFail();
            $entry = AccountEntry::with('items')->whereKey($entry->id)->lockForUpdate()->firstOrFail();

            $itemsById = $entry->items->keyBy('id');
            $subtotal = '0.00';

            foreach ($data['items'] as $itemData) {
                $item = $itemsById->get($itemData['id']);
                if (! $item) {
                    throw ValidationException::withMessages(['items' => 'Um dos itens informados não pertence a esta compra.']);
                }

                $unitPrice = (string) ($itemData['unit_price'] ?? $item->unit_price);
                $discountType = DiscountType::from($itemData['discount_type'] ?? $item->discount_type->value);
                $discountValue = (string) ($itemData['discount_value'] ?? $item->discount_value);
                $gross = bcmul($unitPrice, (string) $item->quantity, 2);
                $discountAmount = $this->resolveDiscountAmount($gross, $discountType, $discountValue);
                $total = bcsub($gross, $discountAmount, 2);
                if (bccomp($total, '0', 2) < 0) {
                    $total = '0.00';
                }

                $item->update([
                    'unit_price' => $unitPrice,
                    'discount_type' => $discountType,
                    'discount_value' => $discountValue,
                    'discount' => $discountAmount,
                    'total' => $total,
                ]);

                $subtotal = bcadd($subtotal, $total, 2);
            }

            $entryDiscountType = DiscountType::from($data['discount_type'] ?? $entry->discount_type?->value ?? DiscountType::Fixed->value);
            $entryDiscountValue = (string) ($data['discount_value'] ?? $entry->discount_value);
            $entryDiscountAmount = $this->resolveDiscountAmount($subtotal, $entryDiscountType, $entryDiscountValue);
            $newAmount = bcsub($subtotal, $entryDiscountAmount, 2);
            if (bccomp($newAmount, '0', 2) < 0) {
                $newAmount = '0.00';
            }

            $balanceAfter = bcadd($account->balance(), bcsub($newAmount, $entry->amount, 2), 2);
            if (bccomp($balanceAfter, '0', 2) < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Essa revisão deixaria o saldo do cliente negativo — o valor já pago é maior que o novo total.',
                ]);
            }

            $entry->update([
                'subtotal' => $subtotal,
                'discount_type' => $entryDiscountType,
                'discount_value' => $entryDiscountValue,
                'discount' => $entryDiscountAmount,
                'amount' => $newAmount,
            ]);

            return $entry->load('items.productVariation.product', 'accountsReceivable.customer');
        });
    }
}
