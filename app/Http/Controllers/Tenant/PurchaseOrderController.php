<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexPurchaseOrderRequest;
use App\Http\Requests\Tenant\StorePurchaseOrderRequest;
use App\Http\Requests\Tenant\UpdatePurchaseOrderRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\PurchaseOrderResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\PurchaseOrder;
use App\Services\Tenant\PurchaseOrderService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Purchase Orders')]
class PurchaseOrderController extends Controller
{
    public function __construct(private PurchaseOrderService $purchaseOrders) {}

    /**
     * @operationId listPurchaseOrders
     */
    public function index(IndexPurchaseOrderRequest $request): ResourceCollection
    {
        return PurchaseOrderResource::collection($this->purchaseOrders->list($request->perPage()))
            ->withMessage('Purchase orders retrieved successfully.');
    }

    /**
     * @operationId createPurchaseOrder
     */
    #[DocsResponse(status: 201, description: 'Purchase order created.', type: 'array{success: true, message: string, data: PurchaseOrderResource, meta: null, errors: null}')]
    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $purchaseOrder = $this->purchaseOrders->create($request->purchaseOrderData());

        return ApiResponse::success(
            data: (new PurchaseOrderResource($purchaseOrder))->resolve(),
            message: 'Purchase order created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showPurchaseOrder
     */
    #[PathParameter('purchase_order', description: 'Purchase order ID.', type: 'integer', example: 1)]
    public function show(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorize('view', $purchaseOrder);

        return (new PurchaseOrderResource($this->purchaseOrders->find($purchaseOrder)))
            ->withMessage('Purchase order retrieved successfully.');
    }

    /**
     * @operationId updatePurchaseOrder
     */
    #[PathParameter('purchase_order', description: 'Purchase order ID.', type: 'integer', example: 1)]
    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return (new PurchaseOrderResource($this->purchaseOrders->update($purchaseOrder, $request->purchaseOrderData())))
            ->withMessage('Purchase order updated successfully.');
    }

    /**
     * @operationId deletePurchaseOrder
     */
    #[PathParameter('purchase_order', description: 'Purchase order ID.', type: 'integer', example: 1)]
    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('delete', $purchaseOrder);
        $this->purchaseOrders->delete($purchaseOrder);

        return ApiResponse::success(message: 'Purchase order deleted successfully.');
    }

    /**
     * @operationId submitPurchaseOrder
     */
    #[PathParameter('purchase_order', description: 'Purchase order ID.', type: 'integer', example: 1)]
    public function submit(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorize('submit', $purchaseOrder);

        return (new PurchaseOrderResource($this->purchaseOrders->submit($purchaseOrder)))
            ->withMessage('Purchase order submitted successfully.');
    }

    /**
     * @operationId approvePurchaseOrder
     */
    #[PathParameter('purchase_order', description: 'Purchase order ID.', type: 'integer', example: 1)]
    public function approve(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorize('approve', $purchaseOrder);

        return (new PurchaseOrderResource($this->purchaseOrders->approve($purchaseOrder)))
            ->withMessage('Purchase order approved successfully.');
    }

    /**
     * @operationId cancelPurchaseOrder
     */
    #[PathParameter('purchase_order', description: 'Purchase order ID.', type: 'integer', example: 1)]
    public function cancel(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorize('cancel', $purchaseOrder);

        return (new PurchaseOrderResource($this->purchaseOrders->cancel($purchaseOrder)))
            ->withMessage('Purchase order cancelled successfully.');
    }
}
