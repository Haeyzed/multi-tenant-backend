<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexWorkOrderRequest;
use App\Http\Requests\Tenant\StoreWorkOrderRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\WorkOrderResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\WorkOrder;
use App\Services\Tenant\WorkOrderService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Work Orders')]
class WorkOrderController extends Controller
{
    public function __construct(private WorkOrderService $workOrders) {}

    /**
     * @operationId listWorkOrders
     */
    public function index(IndexWorkOrderRequest $request): ResourceCollection
    {
        return WorkOrderResource::collection($this->workOrders->list($request->perPage()))
            ->withMessage('Work orders retrieved successfully.');
    }

    /**
     * @operationId createWorkOrder
     */
    #[DocsResponse(status: 201, description: 'Work order created.', type: 'array{success: true, message: string, data: WorkOrderResource, meta: null, errors: null}')]
    public function store(StoreWorkOrderRequest $request): JsonResponse
    {
        $workOrder = $this->workOrders->create($request->workOrderData());

        return ApiResponse::success(
            data: (new WorkOrderResource($workOrder))->resolve(),
            message: 'Work order created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showWorkOrder
     */
    #[PathParameter('work_order', description: 'Work order ID.', type: 'integer', example: 1)]
    public function show(WorkOrder $workOrder): WorkOrderResource
    {
        $this->authorize('view', $workOrder);

        return (new WorkOrderResource($this->workOrders->find($workOrder)))
            ->withMessage('Work order retrieved successfully.');
    }

    /**
     * @operationId deleteWorkOrder
     */
    #[PathParameter('work_order', description: 'Work order ID.', type: 'integer', example: 1)]
    public function destroy(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('delete', $workOrder);
        $this->workOrders->delete($workOrder);

        return ApiResponse::success(message: 'Work order deleted successfully.');
    }

    /**
     * @operationId releaseWorkOrder
     */
    #[PathParameter('work_order', description: 'Work order ID.', type: 'integer', example: 1)]
    public function release(WorkOrder $workOrder): WorkOrderResource
    {
        $this->authorize('release', $workOrder);

        return (new WorkOrderResource($this->workOrders->release($workOrder)))
            ->withMessage('Work order released successfully.');
    }

    /**
     * @operationId completeWorkOrder
     */
    #[PathParameter('work_order', description: 'Work order ID.', type: 'integer', example: 1)]
    public function complete(WorkOrder $workOrder): WorkOrderResource
    {
        $this->authorize('complete', $workOrder);

        return (new WorkOrderResource($this->workOrders->complete($workOrder)))
            ->withMessage('Work order completed successfully.');
    }

    /**
     * @operationId cancelWorkOrder
     */
    #[PathParameter('work_order', description: 'Work order ID.', type: 'integer', example: 1)]
    public function cancel(WorkOrder $workOrder): WorkOrderResource
    {
        $this->authorize('cancel', $workOrder);

        return (new WorkOrderResource($this->workOrders->cancel($workOrder)))
            ->withMessage('Work order cancelled successfully.');
    }
}
