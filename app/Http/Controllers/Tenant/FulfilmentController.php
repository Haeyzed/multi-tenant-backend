<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexFulfilmentRequest;
use App\Http\Requests\Tenant\StoreFulfilmentRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\FulfilmentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Fulfilment;
use App\Services\Tenant\FulfilmentService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Fulfilments')]
class FulfilmentController extends Controller
{
    public function __construct(private FulfilmentService $fulfilments) {}

    /**
     * @operationId listFulfilments
     */
    public function index(IndexFulfilmentRequest $request): ResourceCollection
    {
        return FulfilmentResource::collection($this->fulfilments->list($request->perPage()))
            ->withMessage('Fulfilments retrieved successfully.');
    }

    /**
     * @operationId createFulfilment
     */
    #[DocsResponse(status: 201, description: 'Fulfilment created.', type: 'array{success: true, message: string, data: FulfilmentResource, meta: null, errors: null}')]
    public function store(StoreFulfilmentRequest $request): JsonResponse
    {
        $fulfilment = $this->fulfilments->create($request->fulfilmentData());

        return ApiResponse::success(
            data: (new FulfilmentResource($fulfilment))->resolve(),
            message: 'Fulfilment created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showFulfilment
     */
    #[PathParameter('fulfilment', description: 'Fulfilment ID.', type: 'integer', example: 1)]
    public function show(Fulfilment $fulfilment): FulfilmentResource
    {
        $this->authorize('view', $fulfilment);

        return (new FulfilmentResource($this->fulfilments->find($fulfilment)))
            ->withMessage('Fulfilment retrieved successfully.');
    }

    /**
     * @operationId deleteFulfilment
     */
    #[PathParameter('fulfilment', description: 'Fulfilment ID.', type: 'integer', example: 1)]
    public function destroy(Fulfilment $fulfilment): JsonResponse
    {
        $this->authorize('delete', $fulfilment);
        $this->fulfilments->delete($fulfilment);

        return ApiResponse::success(message: 'Fulfilment deleted successfully.');
    }

    /**
     * @operationId completeFulfilment
     */
    #[PathParameter('fulfilment', description: 'Fulfilment ID.', type: 'integer', example: 1)]
    public function complete(Fulfilment $fulfilment): FulfilmentResource
    {
        $this->authorize('complete', $fulfilment);

        return (new FulfilmentResource($this->fulfilments->complete($fulfilment)))
            ->withMessage('Fulfilment completed successfully.');
    }

    /**
     * @operationId cancelFulfilment
     */
    #[PathParameter('fulfilment', description: 'Fulfilment ID.', type: 'integer', example: 1)]
    public function cancel(Fulfilment $fulfilment): FulfilmentResource
    {
        $this->authorize('cancel', $fulfilment);

        return (new FulfilmentResource($this->fulfilments->cancel($fulfilment)))
            ->withMessage('Fulfilment cancelled successfully.');
    }
}
