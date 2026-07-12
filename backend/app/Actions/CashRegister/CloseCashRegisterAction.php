<?php

namespace App\Actions\CashRegister;

use App\Enums\CashRegisterStatus;
use App\Exceptions\CashRegisterAlreadyClosedException;
use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CloseCashRegisterAction
{
    public function execute(CashRegister $cashRegister, array $data, User $user): CashRegister
    {
        return DB::transaction(function () use ($cashRegister, $data, $user) {
            $locked = CashRegister::whereKey($cashRegister->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === CashRegisterStatus::Closed) {
                throw new CashRegisterAlreadyClosedException();
            }

            $locked->update([
                'status' => CashRegisterStatus::Closed,
                'closed_at' => now(),
                'closing_amount' => $data['closing_amount'],
                'closed_by' => $user->id,
                'notes' => $data['notes'] ?? $locked->notes,
            ]);

            return $locked->fresh();
        });
    }
}
