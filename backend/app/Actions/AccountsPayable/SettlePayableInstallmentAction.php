<?php

namespace App\Actions\AccountsPayable;

use App\Enums\AccountsPayableStatus;
use App\Enums\InstallmentStatus;
use App\Models\PayableInstallment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettlePayableInstallmentAction
{
    public function execute(PayableInstallment $installment, array $data, User $user): PayableInstallment
    {
        return DB::transaction(function () use ($installment, $data) {
            $installment = PayableInstallment::whereKey($installment->id)->lockForUpdate()->firstOrFail();

            if ($installment->status === InstallmentStatus::Paid) {
                throw ValidationException::withMessages([
                    'installment' => 'Esta parcela já foi baixada.',
                ]);
            }

            $installment->update([
                'status' => InstallmentStatus::Paid,
                'paid_at' => now(),
                'paid_amount' => (string) ($data['paid_amount'] ?? $installment->amount),
                'notes' => $data['notes'] ?? $installment->notes,
            ]);

            $accountsPayable = $installment->accountsPayable;

            $hasPendingSiblings = $accountsPayable->installments()
                ->where('status', InstallmentStatus::Pending)
                ->exists();

            if (! $hasPendingSiblings) {
                $accountsPayable->update(['status' => AccountsPayableStatus::Paid]);
            }

            return $installment->load('accountsPayable.supplier');
        });
    }
}
