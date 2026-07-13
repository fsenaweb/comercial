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
            if (in_array($cashOperation->origin, [CashOperationOrigin::Sale, CashOperationOrigin::Adjustment], true)) {
                throw ValidationException::withMessages([
                    'origin' => 'Lançamentos gerados por vendas ou cancelamentos não podem ser removidos — cancele a venda em vez de excluir o lançamento.',
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
