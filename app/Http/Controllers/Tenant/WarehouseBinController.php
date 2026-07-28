<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexWarehouseBinRequest;
use App\Http\Requests\Tenant\StoreWarehouseBinRequest;
use App\Http\Requests\Tenant\UpdateWarehouseBinRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\WarehouseBinResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Warehouse;
use App\Models\Tenant\WarehouseBin;
use App\Services\Tenant\WarehouseTopologyService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Warehouse Bins')]
class WarehouseBinController extends Controller
{
    public function __construct(private WarehouseTopologyService $topology) {}

    /**
     * @operationId listWarehouseBins
     */
    #[PathParameter('warehouse', description: 'Warehouse ID.', type: 'integer', example: 1)]
    public function index(IndexWarehouseBinRequest $request, Warehouse $warehouse): ResourceCollection
    {
        return WarehouseBinResource::collection($this->topology->listBins($warehouse, $request->perPage()))
            ->withMessage('Warehouse bins retrieved successfully.');
    }

    /**
     * @operationId createWarehouseBin
     */
    #[PathParameter('warehouse', description: 'Warehouse ID.', type: 'integer', example: 1)]
    #[DocsResponse(status: 201, description: 'Bin created.', type: 'array{success: true, message: string, data: WarehouseBinResource, meta: null, errors: null}')]
    public function store(StoreWarehouseBinRequest $request, Warehouse $warehouse): JsonResponse
    {
        $bin = $this->topology->createBin($warehouse, $request->binData());

        return ApiResponse::success(
            data: (new WarehouseBinResource($bin->load('zone')))->resolve(),
            message: 'Warehouse bin created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showWarehouseBin
     */
    #[PathParameter('warehouse', description: 'Warehouse ID.', type: 'integer', example: 1)]
    #[PathParameter('bin', description: 'Bin ID.', type: 'integer', example: 1)]
    public function show(Warehouse $warehouse, WarehouseBin $bin): WarehouseBinResource
    {
        abort_unless($bin->warehouse_id === $warehouse->id, 404);
        $this->authorize('view', $bin);

        return (new WarehouseBinResource($this->topology->findBin($bin)))
            ->withMessage('Warehouse bin retrieved successfully.');
    }

    /**
     * @operationId updateWarehouseBin
     */
    #[PathParameter('warehouse', description: 'Warehouse ID.', type: 'integer', example: 1)]
    #[PathParameter('bin', description: 'Bin ID.', type: 'integer', example: 1)]
    public function update(UpdateWarehouseBinRequest $request, Warehouse $warehouse, WarehouseBin $bin): WarehouseBinResource
    {
        abort_unless($bin->warehouse_id === $warehouse->id, 404);

        return (new WarehouseBinResource($this->topology->updateBin($bin, $request->binData())))
            ->withMessage('Warehouse bin updated successfully.');
    }

    /**
     * @operationId deleteWarehouseBin
     */
    #[PathParameter('warehouse', description: 'Warehouse ID.', type: 'integer', example: 1)]
    #[PathParameter('bin', description: 'Bin ID.', type: 'integer', example: 1)]
    public function destroy(Warehouse $warehouse, WarehouseBin $bin): JsonResponse
    {
        abort_unless($bin->warehouse_id === $warehouse->id, 404);
        $this->authorize('delete', $bin);
        $this->topology->deleteBin($bin);

        return ApiResponse::success(message: 'Warehouse bin deleted successfully.');
    }
}
