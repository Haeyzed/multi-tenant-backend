<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexShippingCarrierRequest;
use App\Http\Requests\Tenant\StoreShippingCarrierRequest;
use App\Http\Requests\Tenant\UpdateShippingCarrierRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\ShippingCarrierResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\ShippingCarrier;
use App\Services\Tenant\ShippingCarrierService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Shipping Carriers')]
class ShippingCarrierController extends Controller
{
    public function __construct(private ShippingCarrierService $shippingCarriers) {}

    /**
     * @operationId listShippingCarriers
     */
    public function index(IndexShippingCarrierRequest $request): ResourceCollection
    {
        return ShippingCarrierResource::collection($this->shippingCarriers->list($request->perPage()))
            ->withMessage('Shipping carriers retrieved successfully.');
    }

    /**
     * @operationId createShippingCarrier
     */
    #[DocsResponse(status: 201, description: 'Shipping carrier created.', type: 'array{success: true, message: string, data: ShippingCarrierResource, meta: null, errors: null}')]
    public function store(StoreShippingCarrierRequest $request): JsonResponse
    {
        $shippingCarrier = $this->shippingCarriers->create($request->shippingCarrierData());

        return ApiResponse::success(
            data: (new ShippingCarrierResource($shippingCarrier))->resolve(),
            message: 'Shipping carrier created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showShippingCarrier
     */
    #[PathParameter('shipping_carrier', description: 'Shipping carrier ID.', type: 'integer', example: 1)]
    public function show(ShippingCarrier $shippingCarrier): ShippingCarrierResource
    {
        $this->authorize('view', $shippingCarrier);

        return (new ShippingCarrierResource($this->shippingCarriers->find($shippingCarrier)))
            ->withMessage('Shipping carrier retrieved successfully.');
    }

    /**
     * @operationId updateShippingCarrier
     */
    #[PathParameter('shipping_carrier', description: 'Shipping carrier ID.', type: 'integer', example: 1)]
    public function update(UpdateShippingCarrierRequest $request, ShippingCarrier $shippingCarrier): ShippingCarrierResource
    {
        return (new ShippingCarrierResource($this->shippingCarriers->update($shippingCarrier, $request->shippingCarrierData())))
            ->withMessage('Shipping carrier updated successfully.');
    }

    /**
     * @operationId deleteShippingCarrier
     */
    #[PathParameter('shipping_carrier', description: 'Shipping carrier ID.', type: 'integer', example: 1)]
    public function destroy(ShippingCarrier $shippingCarrier): JsonResponse
    {
        $this->authorize('delete', $shippingCarrier);
        $this->shippingCarriers->delete($shippingCarrier);

        return ApiResponse::success(message: 'Shipping carrier deleted successfully.');
    }
}
