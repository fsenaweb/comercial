<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function cancel(User $user, Sale $sale): bool
    {
        return $user->isAdmin() || $user->isCashier();
    }
}
