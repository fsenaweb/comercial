<?php

namespace App\Actions\User;

use App\Exceptions\SelfDeactivationException;
use App\Models\User;

class UpdateUserAction
{
    public function execute(User $user, array $data, User $actingUser): User
    {
        if ($user->is($actingUser) && $data['active'] === false) {
            throw new SelfDeactivationException();
        }

        // Senha em branco no form de edição não deve sobrescrever a atual.
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return $user->fresh();
    }
}
