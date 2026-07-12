<?php

namespace App\Policies;

use App\Models\User;

class CustomerPolicy
{
    public function create(User $user): bool
    {
        return in_array($user->role->value, ['admin', 'cashier', 'seller'], true);
    }

    public function update(User $user): bool
    {
        return in_array($user->role->value, ['admin', 'cashier', 'seller'], true);
    }

    public function delete(User $user): bool
    {
        return $user->isAdmin();
    }
}
