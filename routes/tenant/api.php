<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\AuthController;
use App\Http\Controllers\Tenant\BillingController;
use App\Http\Controllers\Tenant\BranchController;
use App\Http\Controllers\Tenant\BrandController;
use App\Http\Controllers\Tenant\BusinessSettingController;
use App\Http\Controllers\Tenant\CategoryController;
use App\Http\Controllers\Tenant\CollectionController;
use App\Http\Controllers\Tenant\CustomerAddressController;
use App\Http\Controllers\Tenant\CustomerContactController;
use App\Http\Controllers\Tenant\CustomerController;
use App\Http\Controllers\Tenant\CustomerGroupController;
use App\Http\Controllers\Tenant\CustomerNoteController;
use App\Http\Controllers\Tenant\CustomerTagController;
use App\Http\Controllers\Tenant\EmployeeController;
use App\Http\Controllers\Tenant\InventoryController;
use App\Http\Controllers\Tenant\OrderController;
use App\Http\Controllers\Tenant\ProductController;
use App\Http\Controllers\Tenant\ProductVariantController;
use App\Http\Controllers\Tenant\ReportController;
use App\Http\Controllers\Tenant\SalesInvoiceController;
use App\Http\Controllers\Tenant\StockAdjustmentReasonController;
use App\Http\Controllers\Tenant\StoreConfigController;
use App\Http\Controllers\Tenant\TaxController;
use App\Http\Controllers\Tenant\UserController;
use App\Http\Controllers\Tenant\WarehouseBinController;
use App\Http\Controllers\Tenant\WarehouseController;
use App\Http\Controllers\Tenant\WarehouseTransferController;
use App\Http\Controllers\Tenant\WarehouseZoneController;
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

    Route::middleware(['feature:features.erp.customers_advanced'])->group(function (): void {
        Route::apiResource('customer-groups', CustomerGroupController::class)
            ->names('tenant.customer-groups');

        Route::apiResource('customer-tags', CustomerTagController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->names('tenant.customer-tags');

        Route::put('customers/{customer}/tags', [CustomerTagController::class, 'sync'])
            ->name('tenant.customers.tags.sync');

        Route::apiResource('customers.addresses', CustomerAddressController::class)
            ->parameters(['addresses' => 'address'])
            ->names('tenant.customers.addresses');

        Route::apiResource('customers.contacts', CustomerContactController::class)
            ->parameters(['contacts' => 'contact'])
            ->names('tenant.customers.contacts');

        Route::apiResource('customers.notes', CustomerNoteController::class)
            ->except(['show'])
            ->parameters(['notes' => 'note'])
            ->names('tenant.customers.notes');
    });

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

    Route::middleware(['feature:features.erp.catalogue_advanced'])->group(function (): void {
        Route::apiResource('brands', BrandController::class)
            ->names('tenant.brands');

        Route::post('collections/{collection}/sync-rules', [CollectionController::class, 'syncRules'])
            ->name('tenant.collections.sync-rules');
        Route::put('collections/{collection}/products', [CollectionController::class, 'syncProducts'])
            ->name('tenant.collections.sync-products');
        Route::apiResource('collections', CollectionController::class)
            ->names('tenant.collections');

        Route::get('products/{product}/variants', [ProductVariantController::class, 'index'])
            ->name('tenant.products.variants.index');
        Route::post('products/{product}/variants', [ProductVariantController::class, 'store'])
            ->name('tenant.products.variants.store');
        Route::post('products/{product}/options', [ProductVariantController::class, 'storeOption'])
            ->name('tenant.products.options.store');
    });

    Route::apiResource('taxes', TaxController::class)
        ->names('tenant.taxes');

    Route::post('warehouses/{warehouse}/stock', [WarehouseController::class, 'adjustStock'])
        ->name('tenant.warehouses.stock');
    Route::apiResource('warehouses', WarehouseController::class)
        ->middleware(['feature:features.erp.warehouses'])
        ->middlewareFor('store', 'entitlement:warehouses.max')
        ->names('tenant.warehouses');

    Route::middleware(['feature:features.erp.warehouses'])->group(function (): void {
        Route::apiResource('branches', BranchController::class)->names('tenant.branches');
        Route::get('inventory/ledger', [InventoryController::class, 'ledger'])
            ->name('tenant.inventory.ledger');
        Route::get('inventory/warehouses/{warehouse}/products/{product}/levels', [InventoryController::class, 'levels'])
            ->name('tenant.inventory.levels');

        Route::apiResource('warehouses.zones', WarehouseZoneController::class)
            ->parameters(['zones' => 'zone'])
            ->names('tenant.warehouses.zones');
        Route::apiResource('warehouses.bins', WarehouseBinController::class)
            ->parameters(['bins' => 'bin'])
            ->names('tenant.warehouses.bins');
        Route::apiResource('stock-adjustment-reasons', StockAdjustmentReasonController::class)
            ->names('tenant.stock-adjustment-reasons');
    });

    Route::middleware(['feature:features.erp.warehouse_transfers'])->group(function (): void {
        Route::post('warehouse-transfers/{warehouse_transfer}/submit', [WarehouseTransferController::class, 'submit'])
            ->name('tenant.warehouse-transfers.submit');
        Route::post('warehouse-transfers/{warehouse_transfer}/approve', [WarehouseTransferController::class, 'approve'])
            ->name('tenant.warehouse-transfers.approve');
        Route::post('warehouse-transfers/{warehouse_transfer}/dispatch', [WarehouseTransferController::class, 'dispatch'])
            ->name('tenant.warehouse-transfers.dispatch');
        Route::post('warehouse-transfers/{warehouse_transfer}/receive', [WarehouseTransferController::class, 'receive'])
            ->name('tenant.warehouse-transfers.receive');
        Route::post('warehouse-transfers/{warehouse_transfer}/cancel', [WarehouseTransferController::class, 'cancel'])
            ->name('tenant.warehouse-transfers.cancel');
        Route::apiResource('warehouse-transfers', WarehouseTransferController::class)
            ->names('tenant.warehouse-transfers');
    });

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
