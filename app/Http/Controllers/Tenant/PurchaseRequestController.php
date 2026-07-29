<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ConvertPurchaseRequestRequest;
use App\Http\Requests\Tenant\IndexPurchaseRequestRequest;
use App\Http\Requests\Tenant\StorePurchaseRequestRequest;
use App\Http\Requests\Tenant\UpdatePurchaseRequestRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\PurchaseOrderResource;
use App\Http\Resources\Tenant\PurchaseRequestResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\PurchaseRequest;
use App\Services\Tenant\PurchaseRequestService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Purchase Requests')]
class PurchaseRequestController extends Controller
{
    public function __construct(private PurchaseRequestService $purchaseRequests) {}

    /**
     * @operationId listPurchaseRequests
     */
    public function index(IndexPurchaseRequestRequest $request): ResourceCollection
    {
        return PurchaseRequestResource::collection($this->purchaseRequests->list($request->perPage()))
            ->withMessage('Purchase requests retrieved successfully.');
    }

    /**
     * @operationId createPurchaseRequest
     */
    #[DocsResponse(status: 201, description: 'Purchase request created.', type: 'array{success: true, message: string, data: PurchaseRequestResource, meta: null, errors: null}')]
    public function store(StorePurchaseRequestRequest $request): JsonResponse
    {
        $purchaseRequest = $this->purchaseRequests->create($request->purchaseRequestData());

        return ApiResponse::success(
            data: (new PurchaseRequestResource($purchaseRequest))->resolve(),
            message: 'Purchase request created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showPurchaseRequest
     */
    #[PathParameter('purchase_request', description: 'Purchase request ID.', type: 'integer', example: 1)]
    public function show(PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('view', $purchaseRequest);

        return (new PurchaseRequestResource($this->purchaseRequests->find($purchaseRequest)))
            ->withMessage('Purchase request retrieved successfully.');
    }

    /**
     * @operationId updatePurchaseRequest
     */
    #[PathParameter('purchase_request', description: 'Purchase request ID.', type: 'integer', example: 1)]
    public function update(UpdatePurchaseRequestRequest $request, PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        return (new PurchaseRequestResource($this->purchaseRequests->update($purchaseRequest, $request->purchaseRequestData())))
            ->withMessage('Purchase request updated successfully.');
    }

    /**
     * @operationId deletePurchaseRequest
     */
    #[PathParameter('purchase_request', description: 'Purchase request ID.', type: 'integer', example: 1)]
    public function destroy(PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorize('delete', $purchaseRequest);
        $this->purchaseRequests->delete($purchaseRequest);

        return ApiResponse::success(message: 'Purchase request deleted successfully.');
    }

    /**
     * @operationId submitPurchaseRequest
     */
    #[PathParameter('purchase_request', description: 'Purchase request ID.', type: 'integer', example: 1)]
    public function submit(PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('submit', $purchaseRequest);

        return (new PurchaseRequestResource($this->purchaseRequests->submit($purchaseRequest)))
            ->withMessage('Purchase request submitted successfully.');
    }

    /**
     * @operationId approvePurchaseRequest
     */
    #[PathParameter('purchase_request', description: 'Purchase request ID.', type: 'integer', example: 1)]
    public function approve(PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('approve', $purchaseRequest);

        return (new PurchaseRequestResource($this->purchaseRequests->approve($purchaseRequest)))
            ->withMessage('Purchase request approved successfully.');
    }

    /**
     * @operationId rejectPurchaseRequest
     */
    #[PathParameter('purchase_request', description: 'Purchase request ID.', type: 'integer', example: 1)]
    public function reject(PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('reject', $purchaseRequest);

        return (new PurchaseRequestResource($this->purchaseRequests->reject($purchaseRequest)))
            ->withMessage('Purchase request rejected successfully.');
    }

    /**
     * @operationId convertPurchaseRequest
     */
    #[PathParameter('purchase_request', description: 'Purchase request ID.', type: 'integer', example: 1)]
    public function convert(ConvertPurchaseRequestRequest $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $purchaseOrder = $this->purchaseRequests->convertToPurchaseOrder(
            $purchaseRequest,
            $request->supplierId(),
        );

        return ApiResponse::success(
            data: [
                'purchase_request' => (new PurchaseRequestResource($this->purchaseRequests->find($purchaseRequest->refresh())))->resolve(),
                'purchase_order' => (new PurchaseOrderResource($purchaseOrder))->resolve(),
            ],
            message: 'Purchase request converted to purchase order successfully.',
        );
    }
}
