<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexShippingMethodRequest;
use App\Http\Requests\Tenant\StoreShippingMethodRequest;
use App\Http\Requests\Tenant\UpdateShippingMethodRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\ShippingMethodResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\ShippingMethod;
use App\Services\Tenant\ShippingMethodService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Shipping Methods')]
class ShippingMethodController extends Controller
{
    public function __construct(private ShippingMethodService $shippingMethods) {}

    /**
     * @operationId listShippingMethods
     */
    public function index(IndexShippingMethodRequest $request): ResourceCollection
    {
        return ShippingMethodResource::collection($this->shippingMethods->list($request->perPage()))
            ->withMessage('Shipping methods retrieved successfully.');
    }

    /**
     * @operationId createShippingMethod
     */
    #[DocsResponse(status: 201, description: 'Shipping method created.', type: 'array{success: true, message: string, data: ShippingMethodResource, meta: null, errors: null}')]
    public function store(StoreShippingMethodRequest $request): JsonResponse
    {
        $shippingMethod = $this->shippingMethods->create($request->shippingMethodData());

        return ApiResponse::success(
            data: (new ShippingMethodResource($shippingMethod))->resolve(),
            message: 'Shipping method created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showShippingMethod
     */
    #[PathParameter('shipping_method', description: 'Shipping method ID.', type: 'integer', example: 1)]
    public function show(ShippingMethod $shippingMethod): ShippingMethodResource
    {
        $this->authorize('view', $shippingMethod);

        return (new ShippingMethodResource($this->shippingMethods->find($shippingMethod)))
            ->withMessage('Shipping method retrieved successfully.');
    }

    /**
     * @operationId updateShippingMethod
     */
    #[PathParameter('shipping_method', description: 'Shipping method ID.', type: 'integer', example: 1)]
    public function update(UpdateShippingMethodRequest $request, ShippingMethod $shippingMethod): ShippingMethodResource
    {
        return (new ShippingMethodResource($this->shippingMethods->update($shippingMethod, $request->shippingMethodData())))
            ->withMessage('Shipping method updated successfully.');
    }

    /**
     * @operationId deleteShippingMethod
     */
    #[PathParameter('shipping_method', description: 'Shipping method ID.', type: 'integer', example: 1)]
    public function destroy(ShippingMethod $shippingMethod): JsonResponse
    {
        $this->authorize('delete', $shippingMethod);
        $this->shippingMethods->delete($shippingMethod);

        return ApiResponse::success(message: 'Shipping method deleted successfully.');
    }
}
