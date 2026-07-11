<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StoreSettingController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/store-settings', [StoreSettingController::class, 'show']);
    Route::put('/store-settings', [StoreSettingController::class, 'update'])
        ->middleware('can:update,App\Models\StoreSetting');
});
