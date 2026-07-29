<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AcceptSupplierQuoteRequest;
use App\Http\Requests\Tenant\CancelSupplierRfqRequest;
use App\Http\Requests\Tenant\IndexSupplierQuoteRequest;
use App\Http\Requests\Tenant\IndexSupplierRfqRequest;
use App\Http\Requests\Tenant\RejectSupplierQuoteRequest;
use App\Http\Requests\Tenant\SendSupplierRfqRequest;
use App\Http\Requests\Tenant\StoreSupplierRfqRequest;
use App\Http\Requests\Tenant\SubmitSupplierQuoteRequest;
use App\Http\Requests\Tenant\UpdateSupplierRfqRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\PurchaseOrderResource;
use App\Http\Resources\Tenant\SupplierQuoteResource;
use App\Http\Resources\Tenant\SupplierRfqResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\SupplierQuote;
use App\Models\Tenant\SupplierRfq;
use App\Services\Tenant\RfqService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Supplier RFQs')]
class SupplierRfqController extends Controller
{
    public function __construct(private RfqService $rfqs) {}

    /**
     * @operationId listSupplierRfqs
     */
    public function index(IndexSupplierRfqRequest $request): ResourceCollection
    {
        return SupplierRfqResource::collection($this->rfqs->list($request->perPage()))
            ->withMessage('Supplier RFQs retrieved successfully.');
    }

    /**
     * @operationId createSupplierRfq
     */
    #[DocsResponse(status: 201, description: 'Supplier RFQ created.', type: 'array{success: true, message: string, data: SupplierRfqResource, meta: null, errors: null}')]
    public function store(StoreSupplierRfqRequest $request): JsonResponse
    {
        $rfq = $this->rfqs->create($request->rfqData());

        return ApiResponse::success(
            data: (new SupplierRfqResource($rfq))->resolve(),
            message: 'Supplier RFQ created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showSupplierRfq
     */
    #[PathParameter('supplier_rfq', description: 'Supplier RFQ ID.', type: 'integer', example: 1)]
    public function show(SupplierRfq $supplierRfq): SupplierRfqResource
    {
        $this->authorize('view', $supplierRfq);

        return (new SupplierRfqResource($this->rfqs->find($supplierRfq)))
            ->withMessage('Supplier RFQ retrieved successfully.');
    }

    /**
     * @operationId updateSupplierRfq
     */
    #[PathParameter('supplier_rfq', description: 'Supplier RFQ ID.', type: 'integer', example: 1)]
    public function update(UpdateSupplierRfqRequest $request, SupplierRfq $supplierRfq): SupplierRfqResource
    {
        return (new SupplierRfqResource($this->rfqs->update($supplierRfq, $request->rfqData())))
            ->withMessage('Supplier RFQ updated successfully.');
    }

    /**
     * @operationId deleteSupplierRfq
     */
    #[PathParameter('supplier_rfq', description: 'Supplier RFQ ID.', type: 'integer', example: 1)]
    public function destroy(SupplierRfq $supplierRfq): JsonResponse
    {
        $this->authorize('delete', $supplierRfq);
        $this->rfqs->delete($supplierRfq);

        return ApiResponse::success(message: 'Supplier RFQ deleted successfully.');
    }

    /**
     * @operationId sendSupplierRfq
     */
    #[PathParameter('supplier_rfq', description: 'Supplier RFQ ID.', type: 'integer', example: 1)]
    public function send(SendSupplierRfqRequest $request, SupplierRfq $supplierRfq): SupplierRfqResource
    {
        return (new SupplierRfqResource($this->rfqs->send($supplierRfq, $request->supplierIds())))
            ->withMessage('Supplier RFQ sent successfully.');
    }

    /**
     * @operationId cancelSupplierRfq
     */
    #[PathParameter('supplier_rfq', description: 'Supplier RFQ ID.', type: 'integer', example: 1)]
    public function cancel(CancelSupplierRfqRequest $request, SupplierRfq $supplierRfq): SupplierRfqResource
    {
        return (new SupplierRfqResource($this->rfqs->cancel($supplierRfq)))
            ->withMessage('Supplier RFQ cancelled successfully.');
    }

    /**
     * @operationId listSupplierQuotes
     */
    #[PathParameter('supplier_rfq', description: 'Supplier RFQ ID.', type: 'integer', example: 1)]
    public function quotes(IndexSupplierQuoteRequest $request, SupplierRfq $supplierRfq): ResourceCollection
    {
        $this->authorize('view', $supplierRfq);

        return SupplierQuoteResource::collection($this->rfqs->listQuotes($supplierRfq, $request->perPage()))
            ->withMessage('Supplier quotes retrieved successfully.');
    }

    /**
     * @operationId showSupplierQuote
     */
    #[PathParameter('supplier_rfq', description: 'Supplier RFQ ID.', type: 'integer', example: 1)]
    #[PathParameter('supplier_quote', description: 'Supplier quote ID.', type: 'integer', example: 1)]
    public function showQuote(SupplierRfq $supplierRfq, SupplierQuote $supplierQuote): SupplierQuoteResource
    {
        abort_unless($supplierQuote->supplier_rfq_id === $supplierRfq->id, 404);
        $this->authorize('view', $supplierQuote);

        return (new SupplierQuoteResource($this->rfqs->findQuote($supplierQuote)))
            ->withMessage('Supplier quote retrieved successfully.');
    }

    /**
     * @operationId submitSupplierQuote
     */
    #[PathParameter('supplier_rfq', description: 'Supplier RFQ ID.', type: 'integer', example: 1)]
    #[PathParameter('supplier_quote', description: 'Supplier quote ID.', type: 'integer', example: 1)]
    public function submitQuote(SubmitSupplierQuoteRequest $request, SupplierRfq $supplierRfq, SupplierQuote $supplierQuote): SupplierQuoteResource
    {
        abort_unless($supplierQuote->supplier_rfq_id === $supplierRfq->id, 404);

        return (new SupplierQuoteResource($this->rfqs->submitQuote($supplierQuote, $request->quoteData())))
            ->withMessage('Supplier quote submitted successfully.');
    }

    /**
     * @operationId acceptSupplierQuote
     */
    #[PathParameter('supplier_rfq', description: 'Supplier RFQ ID.', type: 'integer', example: 1)]
    #[PathParameter('supplier_quote', description: 'Supplier quote ID.', type: 'integer', example: 1)]
    #[DocsResponse(status: 200, description: 'Quote accepted and purchase order created.', type: 'array{success: true, message: string, data: PurchaseOrderResource, meta: null, errors: null}')]
    public function acceptQuote(AcceptSupplierQuoteRequest $request, SupplierRfq $supplierRfq, SupplierQuote $supplierQuote): PurchaseOrderResource
    {
        abort_unless($supplierQuote->supplier_rfq_id === $supplierRfq->id, 404);

        return (new PurchaseOrderResource($this->rfqs->acceptQuote($supplierQuote, $request->convertData())))
            ->withMessage('Supplier quote accepted and purchase order created.');
    }

    /**
     * @operationId rejectSupplierQuote
     */
    #[PathParameter('supplier_rfq', description: 'Supplier RFQ ID.', type: 'integer', example: 1)]
    #[PathParameter('supplier_quote', description: 'Supplier quote ID.', type: 'integer', example: 1)]
    public function rejectQuote(RejectSupplierQuoteRequest $request, SupplierRfq $supplierRfq, SupplierQuote $supplierQuote): SupplierQuoteResource
    {
        abort_unless($supplierQuote->supplier_rfq_id === $supplierRfq->id, 404);

        return (new SupplierQuoteResource($this->rfqs->rejectQuote($supplierQuote)))
            ->withMessage('Supplier quote rejected successfully.');
    }
}
