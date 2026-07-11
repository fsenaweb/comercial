<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\LoginAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginAction $action): UserResource
    {
        $user = $action->execute($request->string('email')->value(), $request->string('password')->value());

        $request->session()->regenerate();

        return UserResource::make($user);
    }

    public function logout(Request $request): \Illuminate\Http\JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sessão encerrada.']);
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }
}
