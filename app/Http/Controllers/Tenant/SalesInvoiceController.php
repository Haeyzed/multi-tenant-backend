<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexSalesInvoiceRequest;
use App\Http\Requests\Tenant\UpdateSalesInvoiceRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\SalesInvoiceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\SalesInvoice;
use App\Services\Tenant\SalesInvoiceService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Illuminate\Http\JsonResponse;

#[Group('Sales Invoices')]
class SalesInvoiceController extends Controller
{
    public function __construct(private SalesInvoiceService $invoices) {}

    /**
     * @operationId listSalesInvoices
     */
    public function index(IndexSalesInvoiceRequest $request): ResourceCollection
    {
        return SalesInvoiceResource::collection($this->invoices->list($request->perPage()))
            ->withMessage('Sales invoices retrieved successfully.');
    }

    /**
     * @operationId showSalesInvoice
     */
    #[PathParameter('salesInvoice', description: 'Sales invoice ID.', type: 'integer', example: 1)]
    public function show(SalesInvoice $salesInvoice): SalesInvoiceResource
    {
        $this->authorize('view', $salesInvoice);

        return (new SalesInvoiceResource($this->invoices->find($salesInvoice)))
            ->withMessage('Sales invoice retrieved successfully.');
    }

    /**
     * @operationId updateSalesInvoice
     */
    #[PathParameter('salesInvoice', description: 'Sales invoice ID.', type: 'integer', example: 1)]
    public function update(UpdateSalesInvoiceRequest $request, SalesInvoice $salesInvoice): SalesInvoiceResource
    {
        return (new SalesInvoiceResource($this->invoices->update($salesInvoice, $request->invoiceData())))
            ->withMessage('Sales invoice updated successfully.');
    }

    /**
     * @operationId deleteSalesInvoice
     */
    #[PathParameter('salesInvoice', description: 'Sales invoice ID.', type: 'integer', example: 1)]
    public function destroy(SalesInvoice $salesInvoice): JsonResponse
    {
        $this->authorize('delete', $salesInvoice);
        $this->invoices->delete($salesInvoice);

        return ApiResponse::success(message: 'Sales invoice deleted successfully.');
    }
}
