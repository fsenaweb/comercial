<?php

namespace App\Policies;

use App\Models\User;

class CashRegisterPolicy
{
    public function operate(User $user): bool
    {
        return $user->isAdmin() || $user->isCashier();
    }
}
