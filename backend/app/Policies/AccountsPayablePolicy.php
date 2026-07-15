<?php

namespace App\Policies;

use App\Models\User;

class AccountsPayablePolicy
{
    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }
}
