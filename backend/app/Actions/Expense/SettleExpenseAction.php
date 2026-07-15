<?php

namespace App\Actions\Expense;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettleExpenseAction
{
    public function execute(Expense $expense, User $user): Expense
    {
        return DB::transaction(function () use ($expense) {
            $expense = Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();

            if ($expense->status === ExpenseStatus::Paid) {
                throw ValidationException::withMessages([
                    'expense' => 'Esta despesa já foi baixada.',
                ]);
            }

            $expense->update([
                'status' => ExpenseStatus::Paid,
                'paid_at' => now(),
            ]);

            return $expense;
        });
    }
}
