<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductVariationController;
use App\Http\Controllers\Api\StoreSettingController;
use App\Http\Controllers\Api\SubcategoryController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/store-settings', [StoreSettingController::class, 'show']);
    Route::put('/store-settings', [StoreSettingController::class, 'update'])
        ->middleware('can:update,App\Models\StoreSetting');
    Route::post('/store-settings/logo', [StoreSettingController::class, 'logo'])
        ->middleware('can:update,App\Models\StoreSetting');

    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('subcategories', SubcategoryController::class);
    Route::apiResource('brands', BrandController::class);
    Route::apiResource('units', UnitController::class);
    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('products', ProductController::class);
    Route::apiResource('products.variations', ProductVariationController::class)
        ->parameters(['variations' => 'productVariation'])
        ->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('users', UserController::class)->only(['index', 'store', 'update']);
});
