<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexShippingZoneRequest;
use App\Http\Requests\Tenant\StoreShippingZoneRequest;
use App\Http\Requests\Tenant\UpdateShippingZoneRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\ShippingZoneResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\ShippingZone;
use App\Services\Tenant\ShippingZoneService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Shipping Zones')]
class ShippingZoneController extends Controller
{
    public function __construct(private ShippingZoneService $shippingZones) {}

    /**
     * @operationId listShippingZones
     */
    public function index(IndexShippingZoneRequest $request): ResourceCollection
    {
        return ShippingZoneResource::collection($this->shippingZones->list($request->perPage()))
            ->withMessage('Shipping zones retrieved successfully.');
    }

    /**
     * @operationId createShippingZone
     */
    #[DocsResponse(status: 201, description: 'Shipping zone created.', type: 'array{success: true, message: string, data: ShippingZoneResource, meta: null, errors: null}')]
    public function store(StoreShippingZoneRequest $request): JsonResponse
    {
        $shippingZone = $this->shippingZones->create($request->shippingZoneData());

        return ApiResponse::success(
            data: (new ShippingZoneResource($shippingZone))->resolve(),
            message: 'Shipping zone created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showShippingZone
     */
    #[PathParameter('shipping_zone', description: 'Shipping zone ID.', type: 'integer', example: 1)]
    public function show(ShippingZone $shippingZone): ShippingZoneResource
    {
        $this->authorize('view', $shippingZone);

        return (new ShippingZoneResource($this->shippingZones->find($shippingZone)))
            ->withMessage('Shipping zone retrieved successfully.');
    }

    /**
     * @operationId updateShippingZone
     */
    #[PathParameter('shipping_zone', description: 'Shipping zone ID.', type: 'integer', example: 1)]
    public function update(UpdateShippingZoneRequest $request, ShippingZone $shippingZone): ShippingZoneResource
    {
        return (new ShippingZoneResource($this->shippingZones->update($shippingZone, $request->shippingZoneData())))
            ->withMessage('Shipping zone updated successfully.');
    }

    /**
     * @operationId deleteShippingZone
     */
    #[PathParameter('shipping_zone', description: 'Shipping zone ID.', type: 'integer', example: 1)]
    public function destroy(ShippingZone $shippingZone): JsonResponse
    {
        $this->authorize('delete', $shippingZone);
        $this->shippingZones->delete($shippingZone);

        return ApiResponse::success(message: 'Shipping zone deleted successfully.');
    }
}
