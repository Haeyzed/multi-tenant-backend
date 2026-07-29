<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexShipmentRequest;
use App\Http\Requests\Tenant\StoreShipmentRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\ShipmentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Shipment;
use App\Services\Tenant\ShipmentService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Shipments')]
class ShipmentController extends Controller
{
    public function __construct(private ShipmentService $shipments) {}

    /**
     * @operationId listShipments
     */
    public function index(IndexShipmentRequest $request): ResourceCollection
    {
        return ShipmentResource::collection($this->shipments->list($request->perPage()))
            ->withMessage('Shipments retrieved successfully.');
    }

    /**
     * @operationId createShipment
     */
    #[DocsResponse(status: 201, description: 'Shipment created.', type: 'array{success: true, message: string, data: ShipmentResource, meta: null, errors: null}')]
    public function store(StoreShipmentRequest $request): JsonResponse
    {
        $shipment = $this->shipments->create($request->shipmentData());

        return ApiResponse::success(
            data: (new ShipmentResource($shipment))->resolve(),
            message: 'Shipment created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showShipment
     */
    #[PathParameter('shipment', description: 'Shipment ID.', type: 'integer', example: 1)]
    public function show(Shipment $shipment): ShipmentResource
    {
        $this->authorize('view', $shipment);

        return (new ShipmentResource($this->shipments->find($shipment)))
            ->withMessage('Shipment retrieved successfully.');
    }

    /**
     * @operationId deleteShipment
     */
    #[PathParameter('shipment', description: 'Shipment ID.', type: 'integer', example: 1)]
    public function destroy(Shipment $shipment): JsonResponse
    {
        $this->authorize('delete', $shipment);
        $this->shipments->delete($shipment);

        return ApiResponse::success(message: 'Shipment deleted successfully.');
    }

    /**
     * @operationId dispatchShipment
     */
    #[PathParameter('shipment', description: 'Shipment ID.', type: 'integer', example: 1)]
    public function dispatch(Shipment $shipment): ShipmentResource
    {
        $this->authorize('dispatch', $shipment);

        return (new ShipmentResource($this->shipments->dispatch($shipment)))
            ->withMessage('Shipment dispatched successfully.');
    }

    /**
     * @operationId deliverShipment
     */
    #[PathParameter('shipment', description: 'Shipment ID.', type: 'integer', example: 1)]
    public function deliver(Shipment $shipment): ShipmentResource
    {
        $this->authorize('deliver', $shipment);

        return (new ShipmentResource($this->shipments->deliver($shipment)))
            ->withMessage('Shipment delivered successfully.');
    }

    /**
     * @operationId cancelShipment
     */
    #[PathParameter('shipment', description: 'Shipment ID.', type: 'integer', example: 1)]
    public function cancel(Shipment $shipment): ShipmentResource
    {
        $this->authorize('cancel', $shipment);

        return (new ShipmentResource($this->shipments->cancel($shipment)))
            ->withMessage('Shipment cancelled successfully.');
    }
}
