<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexSupplierInvoiceRequest;
use App\Http\Requests\Tenant\IssueSupplierInvoiceFromPurchaseOrderRequest;
use App\Http\Requests\Tenant\IssueSupplierInvoiceRequest;
use App\Http\Requests\Tenant\StoreSupplierInvoiceRequest;
use App\Http\Requests\Tenant\UpdateSupplierInvoiceRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\SupplierInvoiceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\SupplierInvoice;
use App\Services\Tenant\SupplierInvoiceService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Supplier Invoices')]
class SupplierInvoiceController extends Controller
{
    public function __construct(private SupplierInvoiceService $invoices) {}

    /**
     * @operationId listSupplierInvoices
     */
    public function index(IndexSupplierInvoiceRequest $request): ResourceCollection
    {
        return SupplierInvoiceResource::collection($this->invoices->list($request->perPage()))
            ->withMessage('Supplier invoices retrieved successfully.');
    }

    /**
     * @operationId createSupplierInvoice
     */
    #[DocsResponse(status: 201, description: 'Supplier invoice created.', type: 'array{success: true, message: string, data: SupplierInvoiceResource, meta: null, errors: null}')]
    public function store(StoreSupplierInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoices->create($request->invoiceData());

        return ApiResponse::success(
            data: (new SupplierInvoiceResource($invoice))->resolve(),
            message: 'Supplier invoice created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showSupplierInvoice
     */
    #[PathParameter('supplier_invoice', description: 'Supplier invoice ID.', type: 'integer', example: 1)]
    public function show(SupplierInvoice $supplierInvoice): SupplierInvoiceResource
    {
        $this->authorize('view', $supplierInvoice);

        return (new SupplierInvoiceResource($this->invoices->find($supplierInvoice)))
            ->withMessage('Supplier invoice retrieved successfully.');
    }

    /**
     * @operationId updateSupplierInvoice
     */
    #[PathParameter('supplier_invoice', description: 'Supplier invoice ID.', type: 'integer', example: 1)]
    public function update(UpdateSupplierInvoiceRequest $request, SupplierInvoice $supplierInvoice): SupplierInvoiceResource
    {
        return (new SupplierInvoiceResource($this->invoices->update($supplierInvoice, $request->invoiceData())))
            ->withMessage('Supplier invoice updated successfully.');
    }

    /**
     * @operationId deleteSupplierInvoice
     */
    #[PathParameter('supplier_invoice', description: 'Supplier invoice ID.', type: 'integer', example: 1)]
    public function destroy(SupplierInvoice $supplierInvoice): JsonResponse
    {
        $this->authorize('delete', $supplierInvoice);
        $this->invoices->delete($supplierInvoice);

        return ApiResponse::success(message: 'Supplier invoice deleted successfully.');
    }

    /**
     * @operationId issueSupplierInvoice
     */
    #[PathParameter('supplier_invoice', description: 'Supplier invoice ID.', type: 'integer', example: 1)]
    public function issue(IssueSupplierInvoiceRequest $request, SupplierInvoice $supplierInvoice): SupplierInvoiceResource
    {
        return (new SupplierInvoiceResource($this->invoices->issue($supplierInvoice)))
            ->withMessage('Supplier invoice issued successfully.');
    }

    /**
     * @operationId issueSupplierInvoiceFromPurchaseOrder
     */
    #[DocsResponse(status: 201, description: 'Supplier invoice issued from purchase order.', type: 'array{success: true, message: string, data: SupplierInvoiceResource, meta: null, errors: null}')]
    public function issueFromPurchaseOrder(IssueSupplierInvoiceFromPurchaseOrderRequest $request): JsonResponse
    {
        $purchaseOrder = PurchaseOrder::query()->findOrFail($request->purchaseOrderId());
        $invoice = $this->invoices->issueFromPurchaseOrder($purchaseOrder);

        return ApiResponse::success(
            data: (new SupplierInvoiceResource($invoice))->resolve(),
            message: 'Supplier invoice issued from purchase order successfully.',
            status: 201,
        );
    }
}
