<?php

namespace App\Http\Controllers\Api;

use App\Actions\User\UpdateAppearanceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateAppearanceRequest;
use App\Http\Resources\UserResource;

class AppearanceController extends Controller
{
    public function update(UpdateAppearanceRequest $request, UpdateAppearanceAction $action): UserResource
    {
        $user = $action->execute($request->user(), $request->validated());

        return UserResource::make($user);
    }
}
