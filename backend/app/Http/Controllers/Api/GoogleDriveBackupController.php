<?php

namespace App\Http\Controllers\Api;

use App\Actions\Backup\DisconnectGoogleDriveAction;
use App\Actions\Backup\PollGoogleDriveConnectionAction;
use App\Actions\Backup\StartGoogleDriveConnectionAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\StoreSettingResource;
use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;

class GoogleDriveBackupController extends Controller
{
    public function connect(StartGoogleDriveConnectionAction $action): JsonResponse
    {
        return response()->json(['data' => $action->execute()]);
    }

    public function status(PollGoogleDriveConnectionAction $action): JsonResponse
    {
        return response()->json(['data' => $action->execute()]);
    }

    public function destroy(DisconnectGoogleDriveAction $action): StoreSettingResource
    {
        $action->execute();

        return StoreSettingResource::make(StoreSetting::current());
    }
}
