<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexOpportunityRequest;
use App\Http\Requests\Tenant\StoreOpportunityRequest;
use App\Http\Requests\Tenant\UpdateOpportunityRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\OpportunityResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Opportunity;
use App\Services\Tenant\OpportunityService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Opportunities')]
class OpportunityController extends Controller
{
    public function __construct(private OpportunityService $opportunities) {}

    /**
     * @operationId listOpportunities
     */
    public function index(IndexOpportunityRequest $request): ResourceCollection
    {
        return OpportunityResource::collection($this->opportunities->list($request->perPage()))
            ->withMessage('Opportunities retrieved successfully.');
    }

    /**
     * @operationId createOpportunity
     */
    #[DocsResponse(status: 201, description: 'Opportunity created.', type: 'array{success: true, message: string, data: OpportunityResource, meta: null, errors: null}')]
    public function store(StoreOpportunityRequest $request): JsonResponse
    {
        $opportunity = $this->opportunities->create($request->opportunityData());

        return ApiResponse::success(
            data: (new OpportunityResource($opportunity))->resolve(),
            message: 'Opportunity created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showOpportunity
     */
    #[PathParameter('opportunity', description: 'Opportunity ID.', type: 'integer', example: 1)]
    public function show(Opportunity $opportunity): OpportunityResource
    {
        $this->authorize('view', $opportunity);

        return (new OpportunityResource($this->opportunities->find($opportunity)))
            ->withMessage('Opportunity retrieved successfully.');
    }

    /**
     * @operationId updateOpportunity
     */
    #[PathParameter('opportunity', description: 'Opportunity ID.', type: 'integer', example: 1)]
    public function update(UpdateOpportunityRequest $request, Opportunity $opportunity): OpportunityResource
    {
        return (new OpportunityResource($this->opportunities->update($opportunity, $request->opportunityData())))
            ->withMessage('Opportunity updated successfully.');
    }

    /**
     * @operationId deleteOpportunity
     */
    #[PathParameter('opportunity', description: 'Opportunity ID.', type: 'integer', example: 1)]
    public function destroy(Opportunity $opportunity): JsonResponse
    {
        $this->authorize('delete', $opportunity);
        $this->opportunities->delete($opportunity);

        return ApiResponse::success(message: 'Opportunity deleted successfully.');
    }

    /**
     * @operationId markOpportunityWon
     */
    #[PathParameter('opportunity', description: 'Opportunity ID.', type: 'integer', example: 1)]
    public function won(Opportunity $opportunity): OpportunityResource
    {
        $this->authorize('markWon', $opportunity);

        return (new OpportunityResource($this->opportunities->markWon($opportunity)))
            ->withMessage('Opportunity marked as won successfully.');
    }

    /**
     * @operationId markOpportunityLost
     */
    #[PathParameter('opportunity', description: 'Opportunity ID.', type: 'integer', example: 1)]
    public function lost(Opportunity $opportunity): OpportunityResource
    {
        $this->authorize('markLost', $opportunity);

        return (new OpportunityResource($this->opportunities->markLost($opportunity)))
            ->withMessage('Opportunity marked as lost successfully.');
    }
}
