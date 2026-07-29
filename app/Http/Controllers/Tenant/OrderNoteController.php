<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreOrderNoteRequest;
use App\Http\Requests\Tenant\UpdateOrderNoteRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\OrderNoteResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderNote;
use App\Services\Tenant\OrderNoteService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Order Notes')]
class OrderNoteController extends Controller
{
    public function __construct(private OrderNoteService $notes) {}

    /**
     * @operationId listOrderNotes
     */
    #[PathParameter('order', description: 'Order ID.', type: 'integer', example: 1)]
    public function index(Request $request, Order $order): ResourceCollection
    {
        $this->authorize('view', $order);

        return OrderNoteResource::collection(
            $this->notes->list($order, (int) $request->integer('per_page', 15))
        )->withMessage('Order notes retrieved successfully.');
    }

    /**
     * @operationId createOrderNote
     */
    #[PathParameter('order', description: 'Order ID.', type: 'integer', example: 1)]
    #[DocsResponse(status: 201, description: 'Order note created.', type: 'array{success: true, message: string, data: OrderNoteResource, meta: null, errors: null}')]
    public function store(StoreOrderNoteRequest $request, Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        $note = $this->notes->create($order, $request->noteData());

        return ApiResponse::success(
            data: (new OrderNoteResource($note))->resolve(),
            message: 'Order note created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showOrderNote
     */
    #[PathParameter('order', description: 'Order ID.', type: 'integer', example: 1)]
    #[PathParameter('note', description: 'Order note ID.', type: 'integer', example: 1)]
    public function show(Order $order, OrderNote $note): OrderNoteResource
    {
        abort_unless($note->order_id === $order->id, 404);
        $this->authorize('view', $note);

        return (new OrderNoteResource($note->load('author')))
            ->withMessage('Order note retrieved successfully.');
    }

    /**
     * @operationId updateOrderNote
     */
    #[PathParameter('order', description: 'Order ID.', type: 'integer', example: 1)]
    #[PathParameter('note', description: 'Order note ID.', type: 'integer', example: 1)]
    public function update(UpdateOrderNoteRequest $request, Order $order, OrderNote $note): OrderNoteResource
    {
        return (new OrderNoteResource($this->notes->update($order, $note, $request->noteData())))
            ->withMessage('Order note updated successfully.');
    }

    /**
     * @operationId deleteOrderNote
     */
    #[PathParameter('order', description: 'Order ID.', type: 'integer', example: 1)]
    #[PathParameter('note', description: 'Order note ID.', type: 'integer', example: 1)]
    public function destroy(Order $order, OrderNote $note): JsonResponse
    {
        abort_unless($note->order_id === $order->id, 404);
        $this->authorize('delete', $note);
        $this->notes->delete($order, $note);

        return ApiResponse::success(message: 'Order note deleted successfully.');
    }
}
