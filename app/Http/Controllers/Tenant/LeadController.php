<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ConvertLeadRequest;
use App\Http\Requests\Tenant\IndexLeadRequest;
use App\Http\Requests\Tenant\StoreLeadRequest;
use App\Http\Requests\Tenant\UpdateLeadRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\LeadResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Lead;
use App\Services\Tenant\LeadService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Leads')]
class LeadController extends Controller
{
    public function __construct(private LeadService $leads) {}

    /**
     * @operationId listLeads
     */
    public function index(IndexLeadRequest $request): ResourceCollection
    {
        return LeadResource::collection($this->leads->list($request->perPage()))
            ->withMessage('Leads retrieved successfully.');
    }

    /**
     * @operationId createLead
     */
    #[DocsResponse(status: 201, description: 'Lead created.', type: 'array{success: true, message: string, data: LeadResource, meta: null, errors: null}')]
    public function store(StoreLeadRequest $request): JsonResponse
    {
        $lead = $this->leads->create($request->leadData());

        return ApiResponse::success(
            data: (new LeadResource($lead))->resolve(),
            message: 'Lead created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showLead
     */
    #[PathParameter('lead', description: 'Lead ID.', type: 'integer', example: 1)]
    public function show(Lead $lead): LeadResource
    {
        $this->authorize('view', $lead);

        return (new LeadResource($this->leads->find($lead)))
            ->withMessage('Lead retrieved successfully.');
    }

    /**
     * @operationId updateLead
     */
    #[PathParameter('lead', description: 'Lead ID.', type: 'integer', example: 1)]
    public function update(UpdateLeadRequest $request, Lead $lead): LeadResource
    {
        return (new LeadResource($this->leads->update($lead, $request->leadData())))
            ->withMessage('Lead updated successfully.');
    }

    /**
     * @operationId deleteLead
     */
    #[PathParameter('lead', description: 'Lead ID.', type: 'integer', example: 1)]
    public function destroy(Lead $lead): JsonResponse
    {
        $this->authorize('delete', $lead);
        $this->leads->delete($lead);

        return ApiResponse::success(message: 'Lead deleted successfully.');
    }

    /**
     * @operationId convertLead
     */
    #[PathParameter('lead', description: 'Lead ID.', type: 'integer', example: 1)]
    public function convert(ConvertLeadRequest $request, Lead $lead): LeadResource
    {
        return (new LeadResource($this->leads->convert($lead, $request->customerId())))
            ->withMessage('Lead converted successfully.');
    }
}
