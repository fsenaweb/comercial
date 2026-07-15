<?php

namespace App\Actions\AccountsReceivable;

use App\Enums\AccountEntryType;
use App\Enums\CashOperationOrigin;
use App\Enums\CashOperationType;
use App\Enums\CashRegisterStatus;
use App\Exceptions\CashRegisterClosedException;
use App\Models\AccountEntry;
use App\Models\AccountsReceivable;
use App\Models\CashOperation;
use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterAccountPaymentAction
{
    /**
     * Registra um pagamento (total ou parcial, "às vezes tudo, às vezes uma
     * boa parte") na conta corrente do cliente. Dinheiro físico entrando na
     * loja — exige caixa aberto e lança `cash_operations`, mesma "regra de
     * ouro" de qualquer operação de caixa. `lockForUpdate()` na conta serve
     * duplo propósito: evita baixa concorrente e garante que o saldo lido
     * pra validar "não pode pagar mais do que deve" é o saldo real no
     * momento da baixa, não uma leitura desatualizada.
     */
    public function execute(AccountsReceivable $account, array $data, User $user): AccountEntry
    {
        return DB::transaction(function () use ($account, $data, $user) {
            $account = AccountsReceivable::whereKey($account->id)->lockForUpdate()->firstOrFail();

            $cashRegister = CashRegister::where('status', CashRegisterStatus::Open)->lockForUpdate()->first();
            if (! $cashRegister) {
                throw new CashRegisterClosedException();
            }

            $balance = $account->balance();
            $amount = (string) $data['amount'];

            if (bccomp($amount, $balance, 2) > 0) {
                throw ValidationException::withMessages([
                    'amount' => 'O valor do pagamento não pode ser maior que o saldo devedor atual (R$ '.number_format((float) $balance, 2, ',', '.').').',
                ]);
            }

            $entry = AccountEntry::create([
                'accounts_receivable_id' => $account->id,
                'type' => AccountEntryType::Payment,
                'amount' => $amount,
                'description' => $data['description'] ?? 'Pagamento de crediário',
                'payment_method_id' => $data['payment_method_id'],
                'created_by' => $user->id,
            ]);

            CashOperation::create([
                'cash_register_id' => $cashRegister->id,
                'user_id' => $user->id,
                'type' => CashOperationType::In,
                'origin' => CashOperationOrigin::AccountsReceivable,
                'reference_id' => $entry->id,
                'payment_method_id' => $data['payment_method_id'],
                'amount' => $amount,
                'notes' => "Pagamento de crediário — {$account->customer->name}",
            ]);

            return $entry->load('accountsReceivable.customer', 'paymentMethod');
        });
    }
}
