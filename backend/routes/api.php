<?php

use App\Http\Controllers\Api\AccountsPayableController;
use App\Http\Controllers\Api\AccountsReceivableController;
use App\Http\Controllers\Api\AppearanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CashRegisterController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\GoogleDriveBackupController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductVariationController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\StoreSettingController;
use App\Http\Controllers\Api\StockEntryController;
use App\Http\Controllers\Api\SubcategoryController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me/appearance', [AppearanceController::class, 'update']);

    Route::get('/store-settings', [StoreSettingController::class, 'show']);
    Route::put('/store-settings', [StoreSettingController::class, 'update'])
        ->middleware('can:update,App\Models\StoreSetting');
    Route::post('/store-settings/logo', [StoreSettingController::class, 'logo'])
        ->middleware('can:update,App\Models\StoreSetting');
    Route::put('/store-settings/label-settings', [StoreSettingController::class, 'labelSettings'])
        ->middleware('can:update,App\Models\StoreSetting');

    Route::middleware('can:update,App\Models\StoreSetting')->group(function () {
        Route::get('/store-settings/google-drive/connect', [GoogleDriveBackupController::class, 'connect']);
        Route::get('/store-settings/google-drive/status', [GoogleDriveBackupController::class, 'status']);
        Route::delete('/store-settings/google-drive', [GoogleDriveBackupController::class, 'destroy']);

        Route::get('/backups', [BackupController::class, 'index']);
        Route::get('/backups/{filename}/download', [BackupController::class, 'download'])
            ->where('filename', '.*');
        Route::post('/backups/upload-latest', [BackupController::class, 'uploadLatest']);
        Route::get('/backups/restore/confirmation-code', [BackupController::class, 'confirmationCode']);
        Route::post('/backups/restore', [BackupController::class, 'restore']);
    });

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
    Route::get('/users/active', [UserController::class, 'active']);
    Route::apiResource('users', UserController::class)->only(['index', 'store', 'update']);

    Route::apiResource('payment-methods', PaymentMethodController::class);

    Route::apiResource('sales', SaleController::class)->only(['index', 'show', 'store']);
    Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel']);
    Route::post('/sales/{sale}/convert', [QuoteController::class, 'convert']);
    Route::post('/quotes', [QuoteController::class, 'store']);

    Route::get('/stock-movements', [StockMovementController::class, 'index']);
    Route::post('/stock-movements/adjustment', [StockMovementController::class, 'adjustment']);
    Route::post('/stock-movements/entries', [StockMovementController::class, 'entry']);

    Route::get('/stock-entries', [StockEntryController::class, 'index']);
    Route::get('/stock-entries/{stockEntry}', [StockEntryController::class, 'show']);
    Route::post('/stock-entries/parse-xml', [StockEntryController::class, 'parseXml']);
    Route::post('/stock-entries', [StockEntryController::class, 'store']);

    Route::get('/cash-registers', [CashRegisterController::class, 'index']);
    Route::get('/cash-registers/current', [CashRegisterController::class, 'current']);
    Route::post('/cash-registers/open', [CashRegisterController::class, 'open']);
    Route::post('/cash-registers/operations', [CashRegisterController::class, 'storeOperation']);
    Route::delete('/cash-registers/operations/{cashOperation}', [CashRegisterController::class, 'destroyOperation']);
    Route::put('/cash-registers/{cashRegister}', [CashRegisterController::class, 'update']);
    Route::post('/cash-registers/{cashRegister}/close', [CashRegisterController::class, 'close']);
    Route::get('/cash-registers/{cashRegister}/operations', [CashRegisterController::class, 'operations']);

    Route::get('/accounts-receivable', [AccountsReceivableController::class, 'index']);
    Route::get('/accounts-receivable/{accountsReceivable}', [AccountsReceivableController::class, 'show']);
    Route::post('/accounts-receivable/debits', [AccountsReceivableController::class, 'storeDebit']);
    Route::put('/accounts-receivable/debits/{accountEntry}', [AccountsReceivableController::class, 'updateDebit']);
    Route::post('/accounts-receivable/{accountsReceivable}/payments', [AccountsReceivableController::class, 'storePayment']);

    Route::get('/accounts-payable', [AccountsPayableController::class, 'index']);
    Route::get('/accounts-payable/{accountsPayable}', [AccountsPayableController::class, 'show']);
    Route::post('/accounts-payable', [AccountsPayableController::class, 'store']);
    Route::post('/accounts-payable/installments/{payableInstallment}/settle', [AccountsPayableController::class, 'settleInstallment']);

    Route::get('/financeiro/overview', [FinanceController::class, 'overview']);

    Route::get('/expenses', [ExpenseController::class, 'index']);
    Route::post('/expenses', [ExpenseController::class, 'store']);
    Route::post('/expenses/{expense}/settle', [ExpenseController::class, 'settle']);

    Route::prefix('reports')->group(function () {
        Route::get('/sales-by-day', [ReportController::class, 'salesByDay']);
        Route::get('/sales-by-product', [ReportController::class, 'salesByProduct']);
        Route::get('/sales-by-seller', [ReportController::class, 'salesBySeller']);
        Route::get('/sales-by-category', [ReportController::class, 'salesByCategory']);
        Route::get('/low-stock', [ReportController::class, 'lowStock']);
        Route::get('/dashboard-summary', [ReportController::class, 'dashboardSummary']);
        Route::get('/catalog/{key}', [ReportController::class, 'show']);
        Route::get('/catalog/{key}/export/pdf', [ReportController::class, 'exportPdf']);
        Route::get('/catalog/{key}/export/excel', [ReportController::class, 'exportExcel']);
        Route::get('/catalog/{key}/print', [ReportController::class, 'print']);
    });
});
