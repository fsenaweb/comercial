<?php

namespace App\Http\Controllers\Api;

use App\Actions\User\CreateUserAction;
use App\Actions\User\UpdateUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserOptionResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        return UserResource::collection(User::orderBy('name')->get());
    }

    /**
     * Lista enxuta (id/nome) de usuários ativos, disponível a qualquer papel
     * autenticado — usada pelo seletor de vendedor no PDV, que qualquer
     * papel (admin/caixa/vendedor) pode trocar durante a venda.
     */
    public function active(): AnonymousResourceCollection
    {
        return UserOptionResource::collection(User::where('active', true)->orderBy('name')->get());
    }

    public function store(StoreUserRequest $request, CreateUserAction $action): UserResource
    {
        $this->authorize('create', User::class);

        return UserResource::make($action->execute($request->validated()));
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $action): UserResource
    {
        $this->authorize('update', User::class);

        return UserResource::make($action->execute($user, $request->validated(), $request->user()));
    }
}
