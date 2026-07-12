<?php

namespace App\Actions\CashRegister;

use App\Enums\CashRegisterStatus;
use App\Exceptions\CashRegisterAlreadyClosedException;
use App\Models\CashRegister;
use Illuminate\Support\Facades\DB;

class UpdateCashRegisterAction
{
    public function execute(CashRegister $cashRegister, array $data): CashRegister
    {
        return DB::transaction(function () use ($cashRegister, $data) {
            $locked = CashRegister::whereKey($cashRegister->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === CashRegisterStatus::Closed) {
                throw new CashRegisterAlreadyClosedException();
            }

            $locked->update([
                'opening_amount' => $data['opening_amount'],
                'notes' => $data['notes'] ?? null,
            ]);

            return $locked->fresh();
        });
    }
}
