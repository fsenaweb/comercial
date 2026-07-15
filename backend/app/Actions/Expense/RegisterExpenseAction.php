<?php

namespace App\Actions\Expense;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterExpenseAction
{
    public function execute(array $data, User $user): Expense
    {
        return DB::transaction(function () use ($data, $user) {
            $paidNow = $data['paid_now'] ?? false;

            return Expense::create([
                'description' => $data['description'],
                'category' => $data['category'] ?? null,
                'amount' => $data['amount'],
                'due_date' => $data['due_date'],
                'status' => $paidNow ? ExpenseStatus::Paid : ExpenseStatus::Pending,
                'paid_at' => $paidNow ? now() : null,
                'created_by' => $user->id,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }
}
