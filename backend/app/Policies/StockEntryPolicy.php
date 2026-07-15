<?php

namespace App\Policies;

use App\Models\User;

class StockEntryPolicy
{
    public function manage(User $user): bool
    {
        return $user->isAdmin() || $user->isCashier();
    }
}
