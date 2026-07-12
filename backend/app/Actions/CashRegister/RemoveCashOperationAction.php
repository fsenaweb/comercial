<?php

namespace App\Actions\CashRegister;

use App\Enums\CashRegisterStatus;
use App\Exceptions\CashRegisterAlreadyClosedException;
use App\Models\CashOperation;
use App\Models\CashRegister;
use Illuminate\Support\Facades\DB;

class RemoveCashOperationAction
{
    public function execute(CashOperation $cashOperation): void
    {
        DB::transaction(function () use ($cashOperation) {
            $cashRegister = CashRegister::whereKey($cashOperation->cash_register_id)->lockForUpdate()->firstOrFail();

            if ($cashRegister->status === CashRegisterStatus::Closed) {
                throw new CashRegisterAlreadyClosedException();
            }

            $cashOperation->delete();
        });
    }
}
