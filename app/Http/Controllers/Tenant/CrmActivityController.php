<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexCrmActivityRequest;
use App\Http\Requests\Tenant\StoreCrmActivityRequest;
use App\Http\Requests\Tenant\UpdateCrmActivityRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\CrmActivityResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\CrmActivity;
use App\Services\Tenant\CrmActivityService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('CRM Activities')]
class CrmActivityController extends Controller
{
    public function __construct(private CrmActivityService $activities) {}

    /**
     * @operationId listCrmActivities
     */
    public function index(IndexCrmActivityRequest $request): ResourceCollection
    {
        return CrmActivityResource::collection($this->activities->list($request->perPage()))
            ->withMessage('CRM activities retrieved successfully.');
    }

    /**
     * @operationId createCrmActivity
     */
    #[DocsResponse(status: 201, description: 'CRM activity created.', type: 'array{success: true, message: string, data: CrmActivityResource, meta: null, errors: null}')]
    public function store(StoreCrmActivityRequest $request): JsonResponse
    {
        $activity = $this->activities->create($request->activityData());

        return ApiResponse::success(
            data: (new CrmActivityResource($activity))->resolve(),
            message: 'CRM activity created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showCrmActivity
     */
    #[PathParameter('crm_activity', description: 'CRM activity ID.', type: 'integer', example: 1)]
    public function show(CrmActivity $crmActivity): CrmActivityResource
    {
        $this->authorize('view', $crmActivity);

        return (new CrmActivityResource($this->activities->find($crmActivity)))
            ->withMessage('CRM activity retrieved successfully.');
    }

    /**
     * @operationId updateCrmActivity
     */
    #[PathParameter('crm_activity', description: 'CRM activity ID.', type: 'integer', example: 1)]
    public function update(UpdateCrmActivityRequest $request, CrmActivity $crmActivity): CrmActivityResource
    {
        return (new CrmActivityResource($this->activities->update($crmActivity, $request->activityData())))
            ->withMessage('CRM activity updated successfully.');
    }

    /**
     * @operationId deleteCrmActivity
     */
    #[PathParameter('crm_activity', description: 'CRM activity ID.', type: 'integer', example: 1)]
    public function destroy(CrmActivity $crmActivity): JsonResponse
    {
        $this->authorize('delete', $crmActivity);
        $this->activities->delete($crmActivity);

        return ApiResponse::success(message: 'CRM activity deleted successfully.');
    }

    /**
     * @operationId completeCrmActivity
     */
    #[PathParameter('crm_activity', description: 'CRM activity ID.', type: 'integer', example: 1)]
    public function complete(CrmActivity $crmActivity): CrmActivityResource
    {
        $this->authorize('complete', $crmActivity);

        return (new CrmActivityResource($this->activities->complete($crmActivity)))
            ->withMessage('CRM activity completed successfully.');
    }
}
