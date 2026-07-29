<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\ApprovalRequestController;
use App\Http\Controllers\Tenant\AttributeController;
use App\Http\Controllers\Tenant\AttributeGroupController;
use App\Http\Controllers\Tenant\AttributeValueController;
use App\Http\Controllers\Tenant\AuthController;
use App\Http\Controllers\Tenant\BillingController;
use App\Http\Controllers\Tenant\BillOfMaterialController;
use App\Http\Controllers\Tenant\BranchController;
use App\Http\Controllers\Tenant\BrandController;
use App\Http\Controllers\Tenant\BusinessSettingController;
use App\Http\Controllers\Tenant\CategoryController;
use App\Http\Controllers\Tenant\ChannelController;
use App\Http\Controllers\Tenant\ChannelInventoryController;
use App\Http\Controllers\Tenant\ChannelProductPriceController;
use App\Http\Controllers\Tenant\CollectionController;
use App\Http\Controllers\Tenant\CreditNoteController;
use App\Http\Controllers\Tenant\CrmActivityController;
use App\Http\Controllers\Tenant\CustomerAddressController;
use App\Http\Controllers\Tenant\CustomerContactController;
use App\Http\Controllers\Tenant\CustomerController;
use App\Http\Controllers\Tenant\CustomerGroupController;
use App\Http\Controllers\Tenant\CustomerNoteController;
use App\Http\Controllers\Tenant\CustomerTagController;
use App\Http\Controllers\Tenant\CustomerWalletController;
use App\Http\Controllers\Tenant\DataJobController;
use App\Http\Controllers\Tenant\EmployeeController;
use App\Http\Controllers\Tenant\ExchangeRateController;
use App\Http\Controllers\Tenant\FulfilmentController;
use App\Http\Controllers\Tenant\GiftCardController;
use App\Http\Controllers\Tenant\GoodsReceiptController;
use App\Http\Controllers\Tenant\InventoryController;
use App\Http\Controllers\Tenant\LeadController;
use App\Http\Controllers\Tenant\NotificationController;
use App\Http\Controllers\Tenant\OpportunityController;
use App\Http\Controllers\Tenant\OrderController;
use App\Http\Controllers\Tenant\OrderNoteController;
use App\Http\Controllers\Tenant\PosSessionController;
use App\Http\Controllers\Tenant\PriceListController;
use App\Http\Controllers\Tenant\ProductAttributeController;
use App\Http\Controllers\Tenant\ProductController;
use App\Http\Controllers\Tenant\ProductMediaController;
use App\Http\Controllers\Tenant\ProductRelationController;
use App\Http\Controllers\Tenant\ProductVariantController;
use App\Http\Controllers\Tenant\PromotionController;
use App\Http\Controllers\Tenant\PurchaseOrderController;
use App\Http\Controllers\Tenant\PurchaseRequestController;
use App\Http\Controllers\Tenant\QuotationController;
use App\Http\Controllers\Tenant\ReportController;
use App\Http\Controllers\Tenant\ReturnAuthorizationController;
use App\Http\Controllers\Tenant\SalesInvoiceController;
use App\Http\Controllers\Tenant\SalesPaymentController;
use App\Http\Controllers\Tenant\ShipmentController;
use App\Http\Controllers\Tenant\ShippingCarrierController;
use App\Http\Controllers\Tenant\ShippingMethodController;
use App\Http\Controllers\Tenant\ShippingZoneController;
use App\Http\Controllers\Tenant\StockAdjustmentReasonController;
use App\Http\Controllers\Tenant\StockCountController;
use App\Http\Controllers\Tenant\StockLotController;
use App\Http\Controllers\Tenant\StockSerialController;
use App\Http\Controllers\Tenant\StoreConfigController;
use App\Http\Controllers\Tenant\SupplierController;
use App\Http\Controllers\Tenant\SupplierInvoiceController;
use App\Http\Controllers\Tenant\SupplierPaymentController;
use App\Http\Controllers\Tenant\SupplierReturnController;
use App\Http\Controllers\Tenant\SupplierRfqController;
use App\Http\Controllers\Tenant\TaxController;
use App\Http\Controllers\Tenant\UnitOfMeasureController;
use App\Http\Controllers\Tenant\UserController;
use App\Http\Controllers\Tenant\WarehouseBinController;
use App\Http\Controllers\Tenant\WarehouseController;
use App\Http\Controllers\Tenant\WarehouseTransferController;
use App\Http\Controllers\Tenant\WarehouseZoneController;
use App\Http\Controllers\Tenant\WebhookEndpointController;
use App\Http\Controllers\Tenant\WorkOrderController;
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

    Route::middleware(['feature:features.erp.pricing'])->group(function (): void {
        Route::post('pricing/preview', [PriceListController::class, 'preview'])
            ->name('tenant.pricing.preview');
        Route::apiResource('price-lists', PriceListController::class)
            ->names('tenant.price-lists');
        Route::apiResource('promotions', PromotionController::class)
            ->names('tenant.promotions');
    });

    Route::middleware(['feature:features.erp.sales_advanced'])->group(function (): void {
        Route::post('quotations/{quotation}/send', [QuotationController::class, 'send'])
            ->name('tenant.quotations.send');
        Route::post('quotations/{quotation}/accept', [QuotationController::class, 'accept'])
            ->name('tenant.quotations.accept');
        Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject'])
            ->name('tenant.quotations.reject');
        Route::apiResource('quotations', QuotationController::class)
            ->names('tenant.quotations');

        Route::post('fulfilments/{fulfilment}/complete', [FulfilmentController::class, 'complete'])
            ->name('tenant.fulfilments.complete');
        Route::post('fulfilments/{fulfilment}/cancel', [FulfilmentController::class, 'cancel'])
            ->name('tenant.fulfilments.cancel');
        Route::apiResource('fulfilments', FulfilmentController::class)
            ->only(['index', 'store', 'show', 'destroy'])
            ->names('tenant.fulfilments');

        Route::post('shipments/{shipment}/dispatch', [ShipmentController::class, 'dispatch'])
            ->name('tenant.shipments.dispatch');
        Route::post('shipments/{shipment}/deliver', [ShipmentController::class, 'deliver'])
            ->name('tenant.shipments.deliver');
        Route::post('shipments/{shipment}/cancel', [ShipmentController::class, 'cancel'])
            ->name('tenant.shipments.cancel');
        Route::apiResource('shipments', ShipmentController::class)
            ->only(['index', 'store', 'show', 'destroy'])
            ->names('tenant.shipments');

        Route::post('credit-notes/{credit_note}/issue', [CreditNoteController::class, 'issue'])
            ->name('tenant.credit-notes.issue');
        Route::post('credit-notes/{credit_note}/void', [CreditNoteController::class, 'void'])
            ->name('tenant.credit-notes.void');
        Route::apiResource('credit-notes', CreditNoteController::class)
            ->only(['index', 'store', 'show', 'destroy'])
            ->parameters(['credit-notes' => 'credit_note'])
            ->names('tenant.credit-notes');

        Route::apiResource('orders.notes', OrderNoteController::class)
            ->parameters(['notes' => 'note'])
            ->names('tenant.orders.notes');
    });

    Route::apiResource('sales-invoices', SalesInvoiceController::class)
        ->only(['index', 'show', 'update', 'destroy'])
        ->names('tenant.sales-invoices');

    Route::apiResource('categories', CategoryController::class)
        ->names('tenant.categories');

    Route::middleware(['feature:features.erp.catalogue_advanced'])->group(function (): void {
        Route::apiResource('brands', BrandController::class)
            ->names('tenant.brands');

        Route::apiResource('attribute-groups', AttributeGroupController::class)
            ->names('tenant.attribute-groups');

        Route::apiResource('attributes.values', AttributeValueController::class)
            ->parameters(['values' => 'value'])
            ->names('tenant.attributes.values');

        Route::apiResource('attributes', AttributeController::class)
            ->names('tenant.attributes');

        Route::apiResource('units-of-measure', UnitOfMeasureController::class)
            ->parameters(['units-of-measure' => 'unit_of_measure'])
            ->names('tenant.units-of-measure');

        Route::get('products/{product}/uoms', [UnitOfMeasureController::class, 'indexProductUoms'])
            ->name('tenant.products.uoms.index');
        Route::post('products/{product}/uoms', [UnitOfMeasureController::class, 'attachProductUom'])
            ->name('tenant.products.uoms.store');
        Route::put('products/{product}/uoms/{product_uom}', [UnitOfMeasureController::class, 'updateProductUom'])
            ->name('tenant.products.uoms.update');
        Route::delete('products/{product}/uoms/{product_uom}', [UnitOfMeasureController::class, 'detachProductUom'])
            ->name('tenant.products.uoms.destroy');

        Route::put('products/{product}/attributes', [ProductAttributeController::class, 'update'])
            ->name('tenant.products.attributes.update');

        Route::apiResource('products.relations', ProductRelationController::class)
            ->parameters(['relations' => 'relation'])
            ->names('tenant.products.relations');

        Route::post('products/{product}/media/upload', [ProductMediaController::class, 'upload'])
            ->name('tenant.products.media.upload');
        Route::apiResource('products.media', ProductMediaController::class)
            ->parameters(['media' => 'medium'])
            ->names('tenant.products.media');

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

    Route::middleware(['feature:features.erp.purchasing'])->group(function (): void {
        Route::post('purchase-orders/{purchase_order}/submit', [PurchaseOrderController::class, 'submit'])
            ->name('tenant.purchase-orders.submit');
        Route::post('purchase-orders/{purchase_order}/approve', [PurchaseOrderController::class, 'approve'])
            ->name('tenant.purchase-orders.approve');
        Route::post('purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])
            ->name('tenant.purchase-orders.cancel');
        Route::apiResource('purchase-orders', PurchaseOrderController::class)
            ->parameters(['purchase-orders' => 'purchase_order'])
            ->names('tenant.purchase-orders');

        Route::post('goods-receipts/{goods_receipt}/post', [GoodsReceiptController::class, 'post'])
            ->name('tenant.goods-receipts.post');
        Route::post('goods-receipts/{goods_receipt}/cancel', [GoodsReceiptController::class, 'cancel'])
            ->name('tenant.goods-receipts.cancel');
        Route::apiResource('goods-receipts', GoodsReceiptController::class)
            ->parameters(['goods-receipts' => 'goods_receipt'])
            ->names('tenant.goods-receipts');

        Route::post('supplier-returns/{supplier_return}/post', [SupplierReturnController::class, 'post'])
            ->name('tenant.supplier-returns.post');
        Route::post('supplier-returns/{supplier_return}/cancel', [SupplierReturnController::class, 'cancel'])
            ->name('tenant.supplier-returns.cancel');
        Route::apiResource('supplier-returns', SupplierReturnController::class)
            ->parameters(['supplier-returns' => 'supplier_return'])
            ->names('tenant.supplier-returns');

        Route::apiResource('suppliers', SupplierController::class)
            ->names('tenant.suppliers');

        Route::post('purchase-requests/{purchase_request}/submit', [PurchaseRequestController::class, 'submit'])
            ->name('tenant.purchase-requests.submit');
        Route::post('purchase-requests/{purchase_request}/approve', [PurchaseRequestController::class, 'approve'])
            ->name('tenant.purchase-requests.approve');
        Route::post('purchase-requests/{purchase_request}/reject', [PurchaseRequestController::class, 'reject'])
            ->name('tenant.purchase-requests.reject');
        Route::post('purchase-requests/{purchase_request}/convert', [PurchaseRequestController::class, 'convert'])
            ->name('tenant.purchase-requests.convert');
        Route::apiResource('purchase-requests', PurchaseRequestController::class)
            ->parameters(['purchase-requests' => 'purchase_request'])
            ->names('tenant.purchase-requests');

        Route::middleware(['feature:features.erp.rfq'])->group(function (): void {
            Route::post('supplier-rfqs/{supplier_rfq}/send', [SupplierRfqController::class, 'send'])
                ->name('tenant.supplier-rfqs.send');
            Route::post('supplier-rfqs/{supplier_rfq}/cancel', [SupplierRfqController::class, 'cancel'])
                ->name('tenant.supplier-rfqs.cancel');
            Route::get('supplier-rfqs/{supplier_rfq}/quotes', [SupplierRfqController::class, 'quotes'])
                ->name('tenant.supplier-rfqs.quotes.index');
            Route::get('supplier-rfqs/{supplier_rfq}/quotes/{supplier_quote}', [SupplierRfqController::class, 'showQuote'])
                ->name('tenant.supplier-rfqs.quotes.show');
            Route::post('supplier-rfqs/{supplier_rfq}/quotes/{supplier_quote}/submit', [SupplierRfqController::class, 'submitQuote'])
                ->name('tenant.supplier-rfqs.quotes.submit');
            Route::post('supplier-rfqs/{supplier_rfq}/quotes/{supplier_quote}/accept', [SupplierRfqController::class, 'acceptQuote'])
                ->name('tenant.supplier-rfqs.quotes.accept');
            Route::post('supplier-rfqs/{supplier_rfq}/quotes/{supplier_quote}/reject', [SupplierRfqController::class, 'rejectQuote'])
                ->name('tenant.supplier-rfqs.quotes.reject');
            Route::apiResource('supplier-rfqs', SupplierRfqController::class)
                ->parameters(['supplier-rfqs' => 'supplier_rfq'])
                ->names('tenant.supplier-rfqs');
        });
    });

    Route::middleware(['feature:features.erp.gift_cards'])->group(function (): void {
        Route::post('gift-cards/check-balance', [GiftCardController::class, 'checkBalance'])
            ->name('tenant.gift-cards.check-balance');
        Route::post('gift-cards/{gift_card}/void', [GiftCardController::class, 'void'])
            ->name('tenant.gift-cards.void');
        Route::post('orders/{order}/redeem-gift-card', [GiftCardController::class, 'redeemOnOrder'])
            ->name('tenant.orders.redeem-gift-card');
        Route::apiResource('gift-cards', GiftCardController::class)
            ->except(['update'])
            ->parameters(['gift-cards' => 'gift_card'])
            ->names('tenant.gift-cards');
    });

    Route::middleware(['feature:features.erp.notifications'])->group(function (): void {
        Route::get('notifications', [NotificationController::class, 'index'])
            ->name('tenant.notifications.index');
        Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
            ->name('tenant.notifications.read');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])
            ->name('tenant.notifications.read-all');
    });

    Route::middleware(['feature:features.erp.returns_shipping'])->group(function (): void {
        Route::post('returns/{return_authorization}/submit', [ReturnAuthorizationController::class, 'submit'])
            ->name('tenant.returns.submit');
        Route::post('returns/{return_authorization}/approve', [ReturnAuthorizationController::class, 'approve'])
            ->name('tenant.returns.approve');
        Route::post('returns/{return_authorization}/receive', [ReturnAuthorizationController::class, 'receive'])
            ->name('tenant.returns.receive');
        Route::post('returns/{return_authorization}/refund', [ReturnAuthorizationController::class, 'refund'])
            ->name('tenant.returns.refund');
        Route::post('returns/{return_authorization}/cancel', [ReturnAuthorizationController::class, 'cancel'])
            ->name('tenant.returns.cancel');
        Route::apiResource('returns', ReturnAuthorizationController::class)
            ->parameters(['returns' => 'return_authorization'])
            ->names('tenant.returns');

        Route::apiResource('shipping-carriers', ShippingCarrierController::class)
            ->names('tenant.shipping-carriers');
        Route::apiResource('shipping-zones', ShippingZoneController::class)
            ->names('tenant.shipping-zones');
        Route::apiResource('shipping-methods', ShippingMethodController::class)
            ->names('tenant.shipping-methods');
    });

    Route::middleware(['feature:features.erp.crm'])->group(function (): void {
        Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])
            ->name('tenant.leads.convert');
        Route::apiResource('leads', LeadController::class)
            ->names('tenant.leads');

        Route::post('opportunities/{opportunity}/won', [OpportunityController::class, 'won'])
            ->name('tenant.opportunities.won');
        Route::post('opportunities/{opportunity}/lost', [OpportunityController::class, 'lost'])
            ->name('tenant.opportunities.lost');
        Route::apiResource('opportunities', OpportunityController::class)
            ->names('tenant.opportunities');

        Route::post('crm-activities/{crm_activity}/complete', [CrmActivityController::class, 'complete'])
            ->name('tenant.crm-activities.complete');
        Route::apiResource('crm-activities', CrmActivityController::class)
            ->parameters(['crm-activities' => 'crm_activity'])
            ->names('tenant.crm-activities');
    });

    Route::middleware(['feature:features.erp.manufacturing'])->group(function (): void {
        Route::apiResource('bill-of-materials', BillOfMaterialController::class)
            ->parameters(['bill-of-materials' => 'bill_of_material'])
            ->names('tenant.bill-of-materials');

        Route::post('work-orders/{work_order}/release', [WorkOrderController::class, 'release'])
            ->name('tenant.work-orders.release');
        Route::post('work-orders/{work_order}/complete', [WorkOrderController::class, 'complete'])
            ->name('tenant.work-orders.complete');
        Route::post('work-orders/{work_order}/cancel', [WorkOrderController::class, 'cancel'])
            ->name('tenant.work-orders.cancel');
        Route::apiResource('work-orders', WorkOrderController::class)
            ->only(['index', 'store', 'show', 'destroy'])
            ->parameters(['work-orders' => 'work_order'])
            ->names('tenant.work-orders');
    });

    Route::middleware(['feature:features.erp.approvals'])->group(function (): void {
        Route::post('approvals/{approval_request}/approve', [ApprovalRequestController::class, 'approve'])
            ->name('tenant.approvals.approve');
        Route::post('approvals/{approval_request}/reject', [ApprovalRequestController::class, 'reject'])
            ->name('tenant.approvals.reject');
        Route::post('approvals/{approval_request}/cancel', [ApprovalRequestController::class, 'cancel'])
            ->name('tenant.approvals.cancel');
        Route::apiResource('approvals', ApprovalRequestController::class)
            ->only(['index', 'store', 'show', 'destroy'])
            ->parameters(['approvals' => 'approval_request'])
            ->names('tenant.approvals');
    });

    Route::middleware(['feature:features.erp.webhooks'])->group(function (): void {
        Route::get('webhook-endpoints/{webhook_endpoint}/deliveries', [WebhookEndpointController::class, 'deliveries'])
            ->name('tenant.webhook-endpoints.deliveries');
        Route::apiResource('webhook-endpoints', WebhookEndpointController::class)
            ->parameters(['webhook-endpoints' => 'webhook_endpoint'])
            ->names('tenant.webhook-endpoints');

        Route::post('data-jobs/{data_job}/cancel', [DataJobController::class, 'cancel'])
            ->name('tenant.data-jobs.cancel');
        Route::apiResource('data-jobs', DataJobController::class)
            ->only(['index', 'store', 'show', 'destroy'])
            ->parameters(['data-jobs' => 'data_job'])
            ->names('tenant.data-jobs');
    });

    Route::middleware(['feature:features.erp.channels'])->group(function (): void {
        Route::post('channels/{channel}/sync-inventory', [ChannelController::class, 'syncInventory'])
            ->name('tenant.channels.sync-inventory');
        Route::post('channels/{channel}/publish-product', [ChannelController::class, 'publishProduct'])
            ->name('tenant.channels.publish-product');
        Route::apiResource('channels', ChannelController::class)
            ->names('tenant.channels');

        Route::apiResource('channels.inventories', ChannelInventoryController::class)
            ->only(['index', 'store', 'destroy'])
            ->parameters(['inventories' => 'channel_inventory'])
            ->names('tenant.channels.inventories');
        Route::apiResource('channels.prices', ChannelProductPriceController::class)
            ->only(['index', 'store', 'destroy'])
            ->parameters(['prices' => 'channel_product_price'])
            ->names('tenant.channels.prices');

        Route::post('pos-sessions/{pos_session}/close', [PosSessionController::class, 'close'])
            ->name('tenant.pos-sessions.close');
        Route::post('pos-sessions/{pos_session}/sale', [PosSessionController::class, 'sale'])
            ->name('tenant.pos-sessions.sale');
        Route::apiResource('pos-sessions', PosSessionController::class)
            ->only(['index', 'store', 'show', 'destroy'])
            ->parameters(['pos-sessions' => 'pos_session'])
            ->names('tenant.pos-sessions');
    });

    Route::middleware(['feature:features.erp.accounts_payable'])->group(function (): void {
        Route::post('supplier-invoices/from-purchase-order', [SupplierInvoiceController::class, 'issueFromPurchaseOrder'])
            ->name('tenant.supplier-invoices.from-purchase-order');
        Route::post('supplier-invoices/{supplier_invoice}/issue', [SupplierInvoiceController::class, 'issue'])
            ->name('tenant.supplier-invoices.issue');
        Route::apiResource('supplier-invoices', SupplierInvoiceController::class)
            ->parameters(['supplier-invoices' => 'supplier_invoice'])
            ->names('tenant.supplier-invoices');

        Route::apiResource('supplier-payments', SupplierPaymentController::class)
            ->parameters(['supplier-payments' => 'supplier_payment'])
            ->names('tenant.supplier-payments');
    });

    Route::middleware(['feature:features.erp.finance_advanced'])->group(function (): void {
        Route::apiResource('sales-payments', SalesPaymentController::class)
            ->parameters(['sales-payments' => 'sales_payment'])
            ->names('tenant.sales-payments');

        Route::apiResource('exchange-rates', ExchangeRateController::class)
            ->only(['index', 'store', 'update'])
            ->parameters(['exchange-rates' => 'exchange_rate'])
            ->names('tenant.exchange-rates');

        Route::get('customers/{customer}/wallet', [CustomerWalletController::class, 'show'])
            ->name('tenant.customers.wallet.show');
        Route::post('customers/{customer}/wallet/credit', [CustomerWalletController::class, 'credit'])
            ->name('tenant.customers.wallet.credit');
        Route::post('customers/{customer}/wallet/debit', [CustomerWalletController::class, 'debit'])
            ->name('tenant.customers.wallet.debit');
    });

    Route::middleware(['feature:features.erp.inventory_advanced'])->group(function (): void {
        Route::apiResource('stock-lots', StockLotController::class)
            ->only(['index', 'show', 'store'])
            ->parameters(['stock-lots' => 'stock_lot'])
            ->names('tenant.stock-lots');
        Route::post('stock-counts/{stock_count}/post', [StockCountController::class, 'post'])
            ->name('tenant.stock-counts.post');
        Route::apiResource('stock-counts', StockCountController::class)
            ->only(['index', 'show', 'store', 'update'])
            ->parameters(['stock-counts' => 'stock_count'])
            ->names('tenant.stock-counts');
        Route::apiResource('stock-serials', StockSerialController::class)
            ->only(['index', 'show'])
            ->parameters(['stock-serials' => 'stock_serial'])
            ->names('tenant.stock-serials');
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
    Route::get('reports/inventory-valuation', [ReportController::class, 'inventoryValuation'])
        ->middleware('feature:features.erp.reports')
        ->name('tenant.reports.inventory-valuation');
    Route::get('reports/gross-profit', [ReportController::class, 'grossProfit'])
        ->middleware('feature:features.erp.reports')
        ->name('tenant.reports.gross-profit');
    Route::get('reports/stock-ageing', [ReportController::class, 'stockAgeing'])
        ->middleware('feature:features.erp.reports')
        ->name('tenant.reports.stock-ageing');
    Route::get('reports/purchase-summary', [ReportController::class, 'purchaseSummary'])
        ->middleware('feature:features.erp.reports')
        ->name('tenant.reports.purchase-summary');
    Route::get('reports/ap-aging', [ReportController::class, 'apAging'])
        ->middleware('feature:features.erp.reports')
        ->name('tenant.reports.ap-aging');
});
