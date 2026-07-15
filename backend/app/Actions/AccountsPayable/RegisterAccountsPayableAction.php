<?php

namespace App\Actions\AccountsPayable;

use App\Actions\Concerns\SplitsInstallments;
use App\Enums\AccountsPayableStatus;
use App\Enums\InstallmentStatus;
use App\Models\AccountsPayable;
use App\Models\PayableInstallment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterAccountsPayableAction
{
    use SplitsInstallments;

    public function execute(array $data, User $user): AccountsPayable
    {
        return DB::transaction(function () use ($data, $user) {
            $this->assertInstallmentsMatchTotal($data['installments'], (string) $data['total_amount']);

            $accountsPayable = AccountsPayable::create([
                'supplier_id' => $data['supplier_id'],
                'description' => $data['description'],
                'total_amount' => $data['total_amount'],
                'installments_count' => count($data['installments']),
                'status' => AccountsPayableStatus::Open,
                'stock_entry_id' => $data['stock_entry_id'] ?? null,
                'created_by' => $user->id,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['installments'] as $installment) {
                PayableInstallment::create([
                    'accounts_payable_id' => $accountsPayable->id,
                    'number' => $installment['number'],
                    'amount' => $installment['amount'],
                    'due_date' => $installment['due_date'],
                    'status' => InstallmentStatus::Pending,
                ]);
            }

            return $accountsPayable->load(['installments', 'supplier']);
        });
    }
}
