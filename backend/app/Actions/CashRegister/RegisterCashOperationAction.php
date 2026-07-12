<?php

namespace App\Actions\CashRegister;

use App\Enums\CashOperationOrigin;
use App\Enums\CashOperationType;
use App\Enums\CashRegisterStatus;
use App\Exceptions\CashRegisterClosedException;
use App\Models\CashOperation;
use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterCashOperationAction
{
    public function execute(array $data, User $user): CashOperation
    {
        return DB::transaction(function () use ($data, $user) {
            $cashRegister = CashRegister::where('status', CashRegisterStatus::Open)->lockForUpdate()->first();

            if (! $cashRegister) {
                throw new CashRegisterClosedException();
            }

            $origin = CashOperationOrigin::from($data['origin']);
            $type = $origin === CashOperationOrigin::CashReinforcement
                ? CashOperationType::In
                : CashOperationType::Out;

            return CashOperation::create([
                'cash_register_id' => $cashRegister->id,
                'user_id' => $user->id,
                'type' => $type,
                'origin' => $origin,
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'amount' => $data['amount'],
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }
}
