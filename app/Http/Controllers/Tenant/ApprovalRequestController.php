<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DecideApprovalRequest;
use App\Http\Requests\Tenant\IndexApprovalRequestRequest;
use App\Http\Requests\Tenant\StoreApprovalRequestRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\ApprovalRequestResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\ApprovalRequest;
use App\Services\Tenant\ApprovalRequestService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Approvals')]
class ApprovalRequestController extends Controller
{
    public function __construct(private ApprovalRequestService $approvals) {}

    /**
     * @operationId listApprovals
     */
    public function index(IndexApprovalRequestRequest $request): ResourceCollection
    {
        return ApprovalRequestResource::collection($this->approvals->list($request->perPage()))
            ->withMessage('Approval requests retrieved successfully.');
    }

    /**
     * @operationId createApproval
     */
    #[DocsResponse(status: 201, description: 'Approval request created.', type: 'array{success: true, message: string, data: ApprovalRequestResource, meta: null, errors: null}')]
    public function store(StoreApprovalRequestRequest $request): JsonResponse
    {
        $approvalRequest = $this->approvals->create($request->approvalRequestData());

        return ApiResponse::success(
            data: (new ApprovalRequestResource($approvalRequest))->resolve(),
            message: 'Approval request created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showApproval
     */
    #[PathParameter('approval_request', description: 'Approval request ID.', type: 'integer', example: 1)]
    public function show(ApprovalRequest $approvalRequest): ApprovalRequestResource
    {
        $this->authorize('view', $approvalRequest);

        return (new ApprovalRequestResource($this->approvals->find($approvalRequest)))
            ->withMessage('Approval request retrieved successfully.');
    }

    /**
     * @operationId deleteApproval
     */
    #[PathParameter('approval_request', description: 'Approval request ID.', type: 'integer', example: 1)]
    public function destroy(ApprovalRequest $approvalRequest): JsonResponse
    {
        $this->authorize('delete', $approvalRequest);
        $this->approvals->delete($approvalRequest);

        return ApiResponse::success(message: 'Approval request deleted successfully.');
    }

    /**
     * @operationId approveApproval
     */
    #[PathParameter('approval_request', description: 'Approval request ID.', type: 'integer', example: 1)]
    public function approve(DecideApprovalRequest $request, ApprovalRequest $approvalRequest): ApprovalRequestResource
    {
        return (new ApprovalRequestResource($this->approvals->approve($approvalRequest, $request->decisionNotes())))
            ->withMessage('Approval request approved successfully.');
    }

    /**
     * @operationId rejectApproval
     */
    #[PathParameter('approval_request', description: 'Approval request ID.', type: 'integer', example: 1)]
    public function reject(DecideApprovalRequest $request, ApprovalRequest $approvalRequest): ApprovalRequestResource
    {
        return (new ApprovalRequestResource($this->approvals->reject($approvalRequest, $request->decisionNotes())))
            ->withMessage('Approval request rejected successfully.');
    }

    /**
     * @operationId cancelApproval
     */
    #[PathParameter('approval_request', description: 'Approval request ID.', type: 'integer', example: 1)]
    public function cancel(ApprovalRequest $approvalRequest): ApprovalRequestResource
    {
        $this->authorize('cancel', $approvalRequest);

        return (new ApprovalRequestResource($this->approvals->cancel($approvalRequest)))
            ->withMessage('Approval request cancelled successfully.');
    }
}
