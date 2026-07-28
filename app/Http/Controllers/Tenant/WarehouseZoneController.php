<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexWarehouseZoneRequest;
use App\Http\Requests\Tenant\StoreWarehouseZoneRequest;
use App\Http\Requests\Tenant\UpdateWarehouseZoneRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\WarehouseZoneResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Warehouse;
use App\Models\Tenant\WarehouseZone;
use App\Services\Tenant\WarehouseTopologyService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Warehouse Zones')]
class WarehouseZoneController extends Controller
{
    public function __construct(private WarehouseTopologyService $topology) {}

    /**
     * @operationId listWarehouseZones
     */
    #[PathParameter('warehouse', description: 'Warehouse ID.', type: 'integer', example: 1)]
    public function index(IndexWarehouseZoneRequest $request, Warehouse $warehouse): ResourceCollection
    {
        return WarehouseZoneResource::collection($this->topology->listZones($warehouse, $request->perPage()))
            ->withMessage('Warehouse zones retrieved successfully.');
    }

    /**
     * @operationId createWarehouseZone
     */
    #[PathParameter('warehouse', description: 'Warehouse ID.', type: 'integer', example: 1)]
    #[DocsResponse(status: 201, description: 'Zone created.', type: 'array{success: true, message: string, data: WarehouseZoneResource, meta: null, errors: null}')]
    public function store(StoreWarehouseZoneRequest $request, Warehouse $warehouse): JsonResponse
    {
        $zone = $this->topology->createZone($warehouse, $request->zoneData());

        return ApiResponse::success(
            data: (new WarehouseZoneResource($zone))->resolve(),
            message: 'Warehouse zone created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showWarehouseZone
     */
    #[PathParameter('warehouse', description: 'Warehouse ID.', type: 'integer', example: 1)]
    #[PathParameter('zone', description: 'Zone ID.', type: 'integer', example: 1)]
    public function show(Warehouse $warehouse, WarehouseZone $zone): WarehouseZoneResource
    {
        abort_unless($zone->warehouse_id === $warehouse->id, 404);
        $this->authorize('view', $zone);

        return (new WarehouseZoneResource($this->topology->findZone($zone)))
            ->withMessage('Warehouse zone retrieved successfully.');
    }

    /**
     * @operationId updateWarehouseZone
     */
    #[PathParameter('warehouse', description: 'Warehouse ID.', type: 'integer', example: 1)]
    #[PathParameter('zone', description: 'Zone ID.', type: 'integer', example: 1)]
    public function update(UpdateWarehouseZoneRequest $request, Warehouse $warehouse, WarehouseZone $zone): WarehouseZoneResource
    {
        abort_unless($zone->warehouse_id === $warehouse->id, 404);

        return (new WarehouseZoneResource($this->topology->updateZone($zone, $request->zoneData())))
            ->withMessage('Warehouse zone updated successfully.');
    }

    /**
     * @operationId deleteWarehouseZone
     */
    #[PathParameter('warehouse', description: 'Warehouse ID.', type: 'integer', example: 1)]
    #[PathParameter('zone', description: 'Zone ID.', type: 'integer', example: 1)]
    public function destroy(Warehouse $warehouse, WarehouseZone $zone): JsonResponse
    {
        abort_unless($zone->warehouse_id === $warehouse->id, 404);
        $this->authorize('delete', $zone);
        $this->topology->deleteZone($zone);

        return ApiResponse::success(message: 'Warehouse zone deleted successfully.');
    }
}
