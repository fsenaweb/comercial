<?php

namespace App\Policies;

use App\Models\User;

class StoreSettingPolicy
{
    public function update(User $user): bool
    {
        return $user->isAdmin();
    }
}
