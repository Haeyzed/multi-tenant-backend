<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AdjustWarehouseStockRequest;
use App\Http\Requests\Tenant\IndexWarehouseRequest;
use App\Http\Requests\Tenant\StoreWarehouseRequest;
use App\Http\Requests\Tenant\UpdateWarehouseRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\WarehouseResource;
use App\Http\Resources\Tenant\WarehouseStockResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Warehouse;
use App\Services\Tenant\WarehouseService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Warehouses')]
class WarehouseController extends Controller
{
    public function __construct(private WarehouseService $warehouses) {}

    /**
     * @operationId listWarehouses
     */
    public function index(IndexWarehouseRequest $request): ResourceCollection
    {
        return WarehouseResource::collection($this->warehouses->list($request->perPage()))
            ->withMessage('Warehouses retrieved successfully.');
    }

    /**
     * @operationId createWarehouse
     */
    #[DocsResponse(status: 201, description: 'Warehouse created.', type: 'array{success: true, message: string, data: WarehouseResource, meta: null, errors: null}')]
    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $warehouse = $this->warehouses->create($request->warehouseData());

        return ApiResponse::success(
            data: (new WarehouseResource($warehouse))->resolve(),
            message: 'Warehouse created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showWarehouse
     */
    #[PathParameter('warehouse', description: 'Warehouse ID.', type: 'integer', example: 1)]
    public function show(Warehouse $warehouse): WarehouseResource
    {
        $this->authorize('view', $warehouse);

        return (new WarehouseResource($this->warehouses->find($warehouse)))
            ->withMessage('Warehouse retrieved successfully.');
    }

    /**
     * @operationId updateWarehouse
     */
    #[PathParameter('warehouse', description: 'Warehouse ID.', type: 'integer', example: 1)]
    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): WarehouseResource
    {
        return (new WarehouseResource($this->warehouses->update($warehouse, $request->warehouseData())))
            ->withMessage('Warehouse updated successfully.');
    }

    /**
     * @operationId deleteWarehouse
     */
    #[PathParameter('warehouse', description: 'Warehouse ID.', type: 'integer', example: 1)]
    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $this->authorize('delete', $warehouse);
        $this->warehouses->delete($warehouse);

        return ApiResponse::success(message: 'Warehouse deleted successfully.');
    }

    /**
     * @operationId adjustWarehouseStock
     */
    #[PathParameter('warehouse', description: 'Warehouse ID.', type: 'integer', example: 1)]
    public function adjustStock(AdjustWarehouseStockRequest $request, Warehouse $warehouse): JsonResponse
    {
        $stock = $this->warehouses->adjustStock(
            $warehouse,
            $request->integer('product_id'),
            $request->integer('quantity'),
            $request->boolean('absolute'),
        );

        return ApiResponse::success(
            data: (new WarehouseStockResource($stock->load(['warehouse', 'product'])))->resolve(),
            message: 'Warehouse stock adjusted successfully.',
        );
    }
}
