<?php

namespace App\Http\Controllers\Api;

use App\Actions\StoreSetting\UpdateStoreSettingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSetting\UpdateStoreSettingRequest;
use App\Http\Resources\StoreSettingResource;
use App\Models\StoreSetting;

class StoreSettingController extends Controller
{
    public function show(): StoreSettingResource
    {
        return StoreSettingResource::make(StoreSetting::current());
    }

    public function update(UpdateStoreSettingRequest $request, UpdateStoreSettingAction $action): StoreSettingResource
    {
        $settings = $action->execute($request->validated());

        return StoreSettingResource::make($settings);
    }
}
