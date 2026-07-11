<?php

namespace App\Actions\Auth;

use App\Exceptions\InactiveUserException;
use App\Exceptions\InvalidCredentialsException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class LoginAction
{
    public function execute(string $email, string $password): User
    {
        if (! Auth::attempt(['email' => $email, 'password' => $password])) {
            throw new InvalidCredentialsException();
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->active) {
            Auth::logout();

            throw new InactiveUserException();
        }

        return $user;
    }
}
