<?php

namespace App\Policies;

use App\Models\User;

class ExpensePolicy
{
    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }
}
