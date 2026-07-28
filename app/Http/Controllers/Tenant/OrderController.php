<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexOrderRequest;
use App\Http\Requests\Tenant\StoreOrderRequest;
use App\Http\Requests\Tenant\UpdateOrderRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\OrderResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Order;
use App\Services\Tenant\OrderService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Orders')]
class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    /**
     * @operationId listOrders
     */
    public function index(IndexOrderRequest $request): ResourceCollection
    {
        return OrderResource::collection($this->orders->list($request->perPage()))
            ->withMessage('Orders retrieved successfully.');
    }

    /**
     * @operationId createOrder
     */
    #[DocsResponse(status: 201, description: 'Order created.', type: 'array{success: true, message: string, data: OrderResource, meta: null, errors: null}')]
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orders->create($request->orderData());

        return ApiResponse::success(
            data: (new OrderResource($order))->resolve(),
            message: 'Order created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showOrder
     */
    #[PathParameter('order', description: 'Order ID.', type: 'integer', example: 1)]
    public function show(Order $order): OrderResource
    {
        $this->authorize('view', $order);

        return (new OrderResource($this->orders->find($order)))
            ->withMessage('Order retrieved successfully.');
    }

    /**
     * @operationId updateOrder
     */
    #[PathParameter('order', description: 'Order ID.', type: 'integer', example: 1)]
    public function update(UpdateOrderRequest $request, Order $order): OrderResource
    {
        return (new OrderResource($this->orders->update($order, $request->orderData())))
            ->withMessage('Order updated successfully.');
    }

    /**
     * @operationId deleteOrder
     */
    #[PathParameter('order', description: 'Order ID.', type: 'integer', example: 1)]
    public function destroy(Order $order): JsonResponse
    {
        $this->authorize('delete', $order);
        $this->orders->delete($order);

        return ApiResponse::success(message: 'Order deleted successfully.');
    }
}
