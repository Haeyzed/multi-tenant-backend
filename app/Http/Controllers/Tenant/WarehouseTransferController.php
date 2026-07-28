<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DispatchWarehouseTransferRequest;
use App\Http\Requests\Tenant\IndexWarehouseTransferRequest;
use App\Http\Requests\Tenant\ReceiveWarehouseTransferRequest;
use App\Http\Requests\Tenant\StoreWarehouseTransferRequest;
use App\Http\Requests\Tenant\UpdateWarehouseTransferRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\WarehouseTransferResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\WarehouseTransfer;
use App\Services\Tenant\TransferService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Warehouse Transfers')]
class WarehouseTransferController extends Controller
{
    public function __construct(private TransferService $transfers) {}

    /**
     * @operationId listWarehouseTransfers
     */
    public function index(IndexWarehouseTransferRequest $request): ResourceCollection
    {
        return WarehouseTransferResource::collection($this->transfers->list($request->perPage()))
            ->withMessage('Warehouse transfers retrieved successfully.');
    }

    /**
     * @operationId createWarehouseTransfer
     */
    #[DocsResponse(status: 201, description: 'Transfer created.', type: 'array{success: true, message: string, data: WarehouseTransferResource, meta: null, errors: null}')]
    public function store(StoreWarehouseTransferRequest $request): JsonResponse
    {
        $transfer = $this->transfers->create($request->transferData());

        return ApiResponse::success(
            data: (new WarehouseTransferResource($transfer))->resolve(),
            message: 'Warehouse transfer created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showWarehouseTransfer
     */
    #[PathParameter('warehouse_transfer', description: 'Warehouse transfer ID.', type: 'integer', example: 1)]
    public function show(WarehouseTransfer $warehouseTransfer): WarehouseTransferResource
    {
        $this->authorize('view', $warehouseTransfer);

        return (new WarehouseTransferResource($this->transfers->find($warehouseTransfer)))
            ->withMessage('Warehouse transfer retrieved successfully.');
    }

    /**
     * @operationId updateWarehouseTransfer
     */
    #[PathParameter('warehouse_transfer', description: 'Warehouse transfer ID.', type: 'integer', example: 1)]
    public function update(UpdateWarehouseTransferRequest $request, WarehouseTransfer $warehouseTransfer): WarehouseTransferResource
    {
        return (new WarehouseTransferResource($this->transfers->update($warehouseTransfer, $request->transferData())))
            ->withMessage('Warehouse transfer updated successfully.');
    }

    /**
     * @operationId deleteWarehouseTransfer
     */
    #[PathParameter('warehouse_transfer', description: 'Warehouse transfer ID.', type: 'integer', example: 1)]
    public function destroy(WarehouseTransfer $warehouseTransfer): JsonResponse
    {
        $this->authorize('delete', $warehouseTransfer);
        $this->transfers->delete($warehouseTransfer);

        return ApiResponse::success(message: 'Warehouse transfer deleted successfully.');
    }

    /**
     * @operationId submitWarehouseTransfer
     */
    #[PathParameter('warehouse_transfer', description: 'Warehouse transfer ID.', type: 'integer', example: 1)]
    public function submit(WarehouseTransfer $warehouseTransfer): WarehouseTransferResource
    {
        $this->authorize('submit', $warehouseTransfer);

        return (new WarehouseTransferResource($this->transfers->submit($warehouseTransfer)))
            ->withMessage('Warehouse transfer submitted successfully.');
    }

    /**
     * @operationId approveWarehouseTransfer
     */
    #[PathParameter('warehouse_transfer', description: 'Warehouse transfer ID.', type: 'integer', example: 1)]
    public function approve(WarehouseTransfer $warehouseTransfer): WarehouseTransferResource
    {
        $this->authorize('approve', $warehouseTransfer);

        return (new WarehouseTransferResource($this->transfers->approve($warehouseTransfer)))
            ->withMessage('Warehouse transfer approved successfully.');
    }

    /**
     * @operationId dispatchWarehouseTransfer
     */
    #[PathParameter('warehouse_transfer', description: 'Warehouse transfer ID.', type: 'integer', example: 1)]
    public function dispatch(DispatchWarehouseTransferRequest $request, WarehouseTransfer $warehouseTransfer): WarehouseTransferResource
    {
        return (new WarehouseTransferResource(
            $this->transfers->dispatch($warehouseTransfer, $request->validated('dispatch_notes'))
        ))->withMessage('Warehouse transfer dispatched successfully.');
    }

    /**
     * @operationId receiveWarehouseTransfer
     */
    #[PathParameter('warehouse_transfer', description: 'Warehouse transfer ID.', type: 'integer', example: 1)]
    public function receive(ReceiveWarehouseTransferRequest $request, WarehouseTransfer $warehouseTransfer): WarehouseTransferResource
    {
        return (new WarehouseTransferResource(
            $this->transfers->receive($warehouseTransfer, $request->receivedItems())
        ))->withMessage('Warehouse transfer received successfully.');
    }

    /**
     * @operationId cancelWarehouseTransfer
     */
    #[PathParameter('warehouse_transfer', description: 'Warehouse transfer ID.', type: 'integer', example: 1)]
    public function cancel(WarehouseTransfer $warehouseTransfer): WarehouseTransferResource
    {
        $this->authorize('cancel', $warehouseTransfer);

        return (new WarehouseTransferResource($this->transfers->cancel($warehouseTransfer)))
            ->withMessage('Warehouse transfer cancelled successfully.');
    }
}
