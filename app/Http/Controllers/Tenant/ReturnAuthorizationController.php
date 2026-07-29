<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexReturnAuthorizationRequest;
use App\Http\Requests\Tenant\StoreReturnAuthorizationRequest;
use App\Http\Requests\Tenant\UpdateReturnAuthorizationRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\ReturnAuthorizationResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\ReturnAuthorization;
use App\Services\Tenant\ReturnAuthorizationService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Returns')]
class ReturnAuthorizationController extends Controller
{
    public function __construct(private ReturnAuthorizationService $returnAuthorizations) {}

    /**
     * @operationId listReturns
     */
    public function index(IndexReturnAuthorizationRequest $request): ResourceCollection
    {
        return ReturnAuthorizationResource::collection($this->returnAuthorizations->list($request->perPage()))
            ->withMessage('Returns retrieved successfully.');
    }

    /**
     * @operationId createReturn
     */
    #[DocsResponse(status: 201, description: 'Return created.', type: 'array{success: true, message: string, data: ReturnAuthorizationResource, meta: null, errors: null}')]
    public function store(StoreReturnAuthorizationRequest $request): JsonResponse
    {
        $returnAuthorization = $this->returnAuthorizations->create($request->returnAuthorizationData());

        return ApiResponse::success(
            data: (new ReturnAuthorizationResource($returnAuthorization))->resolve(),
            message: 'Return created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showReturn
     */
    #[PathParameter('return_authorization', description: 'Return authorization ID.', type: 'integer', example: 1)]
    public function show(ReturnAuthorization $returnAuthorization): ReturnAuthorizationResource
    {
        $this->authorize('view', $returnAuthorization);

        return (new ReturnAuthorizationResource($this->returnAuthorizations->find($returnAuthorization)))
            ->withMessage('Return retrieved successfully.');
    }

    /**
     * @operationId updateReturn
     */
    #[PathParameter('return_authorization', description: 'Return authorization ID.', type: 'integer', example: 1)]
    public function update(UpdateReturnAuthorizationRequest $request, ReturnAuthorization $returnAuthorization): ReturnAuthorizationResource
    {
        return (new ReturnAuthorizationResource($this->returnAuthorizations->update($returnAuthorization, $request->returnAuthorizationData())))
            ->withMessage('Return updated successfully.');
    }

    /**
     * @operationId deleteReturn
     */
    #[PathParameter('return_authorization', description: 'Return authorization ID.', type: 'integer', example: 1)]
    public function destroy(ReturnAuthorization $returnAuthorization): JsonResponse
    {
        $this->authorize('delete', $returnAuthorization);
        $this->returnAuthorizations->delete($returnAuthorization);

        return ApiResponse::success(message: 'Return deleted successfully.');
    }

    /**
     * @operationId submitReturn
     */
    #[PathParameter('return_authorization', description: 'Return authorization ID.', type: 'integer', example: 1)]
    public function submit(ReturnAuthorization $returnAuthorization): ReturnAuthorizationResource
    {
        $this->authorize('submit', $returnAuthorization);

        return (new ReturnAuthorizationResource($this->returnAuthorizations->submit($returnAuthorization)))
            ->withMessage('Return submitted successfully.');
    }

    /**
     * @operationId approveReturn
     */
    #[PathParameter('return_authorization', description: 'Return authorization ID.', type: 'integer', example: 1)]
    public function approve(ReturnAuthorization $returnAuthorization): ReturnAuthorizationResource
    {
        $this->authorize('approve', $returnAuthorization);

        return (new ReturnAuthorizationResource($this->returnAuthorizations->approve($returnAuthorization)))
            ->withMessage('Return approved successfully.');
    }

    /**
     * @operationId receiveReturn
     */
    #[PathParameter('return_authorization', description: 'Return authorization ID.', type: 'integer', example: 1)]
    public function receive(ReturnAuthorization $returnAuthorization): ReturnAuthorizationResource
    {
        $this->authorize('receive', $returnAuthorization);

        return (new ReturnAuthorizationResource($this->returnAuthorizations->receive($returnAuthorization)))
            ->withMessage('Return received successfully.');
    }

    /**
     * @operationId refundReturn
     */
    #[PathParameter('return_authorization', description: 'Return authorization ID.', type: 'integer', example: 1)]
    public function refund(ReturnAuthorization $returnAuthorization): ReturnAuthorizationResource
    {
        $this->authorize('refund', $returnAuthorization);

        return (new ReturnAuthorizationResource($this->returnAuthorizations->refund($returnAuthorization)))
            ->withMessage('Return refunded successfully.');
    }

    /**
     * @operationId cancelReturn
     */
    #[PathParameter('return_authorization', description: 'Return authorization ID.', type: 'integer', example: 1)]
    public function cancel(ReturnAuthorization $returnAuthorization): ReturnAuthorizationResource
    {
        $this->authorize('cancel', $returnAuthorization);

        return (new ReturnAuthorizationResource($this->returnAuthorizations->cancel($returnAuthorization)))
            ->withMessage('Return cancelled successfully.');
    }
}
