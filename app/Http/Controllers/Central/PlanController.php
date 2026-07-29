<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\IndexPlanRequest;
use App\Http\Requests\Central\StorePlanRequest;
use App\Http\Requests\Central\UpdatePlanRequest;
use App\Http\Resources\Central\PlanResource;
use App\Http\Resources\ResourceCollection;
use App\Http\Responses\ApiResponse;
use App\Models\Central\Plan;
use App\Services\Central\PlanService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Plans')]
class PlanController extends Controller
{
    public function __construct(private PlanService $plans) {}

    /**
     * @operationId listPlans
     */
    public function index(IndexPlanRequest $request): ResourceCollection
    {
        return PlanResource::collection($this->plans->list($request->perPage()))
            ->withMessage('Plans retrieved successfully.');
    }

    /**
     * @operationId createPlan
     */
    #[DocsResponse(status: 201, description: 'Plan created.', type: 'array{success: true, message: string, data: PlanResource, meta: null, errors: null}')]
    public function store(StorePlanRequest $request): JsonResponse
    {
        $plan = $this->plans->create($request->planData());

        return ApiResponse::success(
            data: (new PlanResource($plan))->resolve(),
            message: 'Plan created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showPlan
     */
    #[PathParameter('plan', description: 'Plan ID.', type: 'integer', example: 1)]
    public function show(Plan $plan): PlanResource
    {
        $this->authorize('view', $plan);

        return (new PlanResource($this->plans->find($plan)))
            ->withMessage('Plan retrieved successfully.');
    }

    /**
     * @operationId updatePlan
     */
    #[PathParameter('plan', description: 'Plan ID.', type: 'integer', example: 1)]
    public function update(UpdatePlanRequest $request, Plan $plan): PlanResource
    {
        return (new PlanResource($this->plans->update($plan, $request->planData())))
            ->withMessage('Plan updated successfully.');
    }

    /**
     * @operationId deletePlan
     */
    #[PathParameter('plan', description: 'Plan ID.', type: 'integer', example: 1)]
    public function destroy(Plan $plan): JsonResponse
    {
        $this->authorize('delete', $plan);

        $this->plans->delete($plan);

        return ApiResponse::success(message: 'Plan deleted successfully.');
    }
}
