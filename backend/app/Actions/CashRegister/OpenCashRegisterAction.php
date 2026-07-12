<?php

namespace App\Actions\CashRegister;

use App\Enums\CashRegisterStatus;
use App\Exceptions\CashRegisterAlreadyOpenException;
use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OpenCashRegisterAction
{
    public function execute(array $data, User $user): CashRegister
    {
        return DB::transaction(function () use ($data, $user) {
            $existing = CashRegister::where('status', CashRegisterStatus::Open)->lockForUpdate()->first();

            if ($existing) {
                throw new CashRegisterAlreadyOpenException();
            }

            return CashRegister::create([
                'opened_at' => now(),
                'opening_amount' => $data['opening_amount'],
                'status' => CashRegisterStatus::Open,
                'opened_by' => $user->id,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }
}
