<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\AuthController;
use App\Http\Controllers\Tenant\BillingController;
use App\Http\Controllers\Tenant\BusinessSettingController;
use App\Http\Controllers\Tenant\CategoryController;
use App\Http\Controllers\Tenant\CustomerController;
use App\Http\Controllers\Tenant\EmployeeController;
use App\Http\Controllers\Tenant\OrderController;
use App\Http\Controllers\Tenant\ProductController;
use App\Http\Controllers\Tenant\ReportController;
use App\Http\Controllers\Tenant\SalesInvoiceController;
use App\Http\Controllers\Tenant\StoreConfigController;
use App\Http\Controllers\Tenant\TaxController;
use App\Http\Controllers\Tenant\UserController;
use App\Http\Controllers\Tenant\WarehouseController;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant API Routes
|--------------------------------------------------------------------------
|
| Tenant-scoped REST API. Tenancy is already initialized by the parent
| route group in routes/tenant.php.
|
*/

Route::get('/health', function () {
    $data = [
        'context' => 'tenant',
        'tenant_id' => tenant('id'),
        'tenancy_initialized' => tenancy()->initialized,
    ];

    if (! app()->isProduction()) {
        $data['database'] = DB::connection()->getDatabaseName();
    }

    return ApiResponse::success(
        data: $data,
        message: 'Tenant API is healthy.',
    );
})->middleware('throttle:60,1')->name('tenant.health');

Route::prefix('auth')->name('tenant.auth.')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:tenant-auth')
        ->name('login');

    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:tenant-auth')
        ->name('password.email');

    Route::post('reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:tenant-auth')
        ->name('password.update');

    Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::middleware(['auth:sanctum', 'throttle:tenant-api'])->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::put('profile', [AuthController::class, 'updateProfile'])->name('profile.update');
        Route::put('password', [AuthController::class, 'changePassword'])->name('password.change');
        Route::post('email/verification-notification', [AuthController::class, 'sendVerificationEmail'])
            ->middleware('throttle:6,1')
            ->name('verification.send');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    });
});

Route::middleware(['auth:sanctum', 'throttle:tenant-api'])->group(function (): void {
    Route::get('billing/entitlements', [BillingController::class, 'entitlements'])
        ->name('tenant.billing.entitlements');
    Route::get('billing/plans', [BillingController::class, 'plans'])
        ->middleware('feature:features.billing.self_serve')
        ->name('tenant.billing.plans');
    Route::get('billing/subscription', [BillingController::class, 'subscription'])
        ->name('tenant.billing.subscription');
    Route::post('billing/subscribe', [BillingController::class, 'subscribe'])
        ->middleware('feature:features.billing.self_serve')
        ->name('tenant.billing.subscribe');
    Route::post('billing/cancel', [BillingController::class, 'cancel'])
        ->middleware('feature:features.billing.self_serve')
        ->name('tenant.billing.cancel');
    Route::post('billing/resume', [BillingController::class, 'resume'])
        ->middleware('feature:features.billing.self_serve')
        ->name('tenant.billing.resume');
    Route::post('billing/change-plan', [BillingController::class, 'changePlan'])
        ->middleware('feature:features.billing.self_serve')
        ->name('tenant.billing.change-plan');
    Route::get('billing/invoices', [BillingController::class, 'invoices'])
        ->name('tenant.billing.invoices');
    Route::get('billing/invoices/{invoice}', [BillingController::class, 'showInvoice'])
        ->name('tenant.billing.invoices.show');

    Route::apiResource('users', UserController::class)
        ->middlewareFor('store', 'entitlement:users.max')
        ->names('tenant.users');

    Route::apiResource('customers', CustomerController::class)
        ->middlewareFor('store', 'entitlement:customers.max')
        ->names('tenant.customers');
    Route::apiResource('products', ProductController::class)
        ->middlewareFor('store', 'entitlement:products.max')
        ->names('tenant.products');
    Route::apiResource('orders', OrderController::class)
        ->middlewareFor('store', 'entitlement:orders.max')
        ->names('tenant.orders');
    Route::apiResource('sales-invoices', SalesInvoiceController::class)
        ->only(['index', 'show', 'update', 'destroy'])
        ->names('tenant.sales-invoices');

    Route::apiResource('categories', CategoryController::class)
        ->names('tenant.categories');

    Route::apiResource('taxes', TaxController::class)
        ->names('tenant.taxes');

    Route::post('warehouses/{warehouse}/stock', [WarehouseController::class, 'adjustStock'])
        ->name('tenant.warehouses.stock');
    Route::apiResource('warehouses', WarehouseController::class)
        ->middleware(['feature:features.erp.warehouses'])
        ->middlewareFor('store', 'entitlement:warehouses.max')
        ->names('tenant.warehouses');

    Route::apiResource('employees', EmployeeController::class)
        ->middleware(['feature:features.erp.employees'])
        ->middlewareFor('store', 'entitlement:employees.max')
        ->names('tenant.employees');

    Route::get('settings', [BusinessSettingController::class, 'index'])->name('tenant.settings.index');
    Route::get('settings/map', [BusinessSettingController::class, 'map'])->name('tenant.settings.map');
    Route::put('settings', [BusinessSettingController::class, 'upsert'])->name('tenant.settings.upsert');
    Route::get('settings/{setting}', [BusinessSettingController::class, 'show'])
        ->where('setting', '[\w.-]+')
        ->name('tenant.settings.show');
    Route::delete('settings/{setting}', [BusinessSettingController::class, 'destroy'])
        ->where('setting', '[\w.-]+')
        ->name('tenant.settings.destroy');

    Route::get('store-config', [StoreConfigController::class, 'show'])->name('tenant.store-config.show');
    Route::put('store-config', [StoreConfigController::class, 'update'])->name('tenant.store-config.update');

    Route::get('reports/sales-summary', [ReportController::class, 'salesSummary'])
        ->middleware('feature:features.erp.reports')
        ->name('tenant.reports.sales-summary');
    Route::get('reports/top-products', [ReportController::class, 'topProducts'])
        ->middleware('feature:features.erp.reports')
        ->name('tenant.reports.top-products');
    Route::get('reports/low-stock', [ReportController::class, 'lowStock'])
        ->middleware('feature:features.erp.reports')
        ->name('tenant.reports.low-stock');
});
