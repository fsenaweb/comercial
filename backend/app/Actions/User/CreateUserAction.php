<?php

namespace App\Actions\User;

use App\Models\User;

class CreateUserAction
{
    public function execute(array $data): User
    {
        return User::create($data);
    }
}
