<?php

namespace App\Policies;

use App\Models\User;

class AccountsReceivablePolicy
{
    public function manage(User $user): bool
    {
        return $user->isAdmin() || $user->isCashier();
    }
}
