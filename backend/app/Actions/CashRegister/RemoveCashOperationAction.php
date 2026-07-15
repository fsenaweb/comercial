<?php

namespace App\Actions\CashRegister;

use App\Enums\CashOperationOrigin;
use App\Enums\CashRegisterStatus;
use App\Exceptions\CashRegisterAlreadyClosedException;
use App\Models\CashOperation;
use App\Models\CashRegister;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveCashOperationAction
{
    public function execute(CashOperation $cashOperation): void
    {
        DB::transaction(function () use ($cashOperation) {
            if (in_array($cashOperation->origin, [CashOperationOrigin::Sale, CashOperationOrigin::Adjustment, CashOperationOrigin::AccountsReceivable], true)) {
                throw ValidationException::withMessages([
                    'origin' => 'Lançamentos gerados por vendas, cancelamentos ou baixa de crediário não podem ser removidos.',
                ]);
            }

            $cashRegister = CashRegister::whereKey($cashOperation->cash_register_id)->lockForUpdate()->firstOrFail();

            if ($cashRegister->status === CashRegisterStatus::Closed) {
                throw new CashRegisterAlreadyClosedException();
            }

            $cashOperation->delete();
        });
    }
}
