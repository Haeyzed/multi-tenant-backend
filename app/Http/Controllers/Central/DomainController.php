<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\StoreDomainRequest;
use App\Http\Requests\Central\UpdateDomainRequest;
use App\Http\Resources\Central\DomainResource;
use App\Http\Resources\ResourceCollection;
use App\Http\Responses\ApiResponse;
use App\Models\Central\Domain;
use App\Models\Central\Tenant;
use App\Services\Central\DomainService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

/**
 * Central multi-domain management for provisioned tenants.
 */
#[Group('Domains')]
class DomainController extends Controller
{
    public function __construct(private DomainService $domains) {}

    /**
     * List domains for a tenant.
     *
     * @operationId listTenantDomains
     *
     * @param  Tenant  $tenant  The tenant identified by its UUID primary key.
     */
    #[PathParameter('tenant', description: 'Tenant UUID.', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')]
    public function index(Tenant $tenant): ResourceCollection
    {
        $this->authorize('view', $tenant);

        return DomainResource::collection($this->domains->list($tenant))
            ->withMessage('Domains retrieved successfully.');
    }

    /**
     * Add a domain to a tenant.
     *
     * @operationId createTenantDomain
     *
     * @param  Tenant  $tenant  The tenant identified by its UUID primary key.
     */
    #[PathParameter('tenant', description: 'Tenant UUID.', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')]
    #[DocsResponse(
        status: 201,
        description: 'Domain created successfully.',
        type: 'array{success: true, message: string, data: DomainResource, meta: null, errors: null}',
    )]
    public function store(StoreDomainRequest $request, Tenant $tenant): JsonResponse
    {
        $domain = $this->domains->create($tenant, $request->domainData());

        return ApiResponse::success(
            data: (new DomainResource($domain))->resolve(),
            message: 'Domain created successfully.',
            status: 201,
        );
    }

    /**
     * Update a tenant domain hostname.
     *
     * @operationId updateTenantDomain
     *
     * @param  Tenant  $tenant  The tenant identified by its UUID primary key.
     * @param  Domain  $domain  The domain row primary key.
     */
    #[PathParameter('tenant', description: 'Tenant UUID.', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')]
    #[PathParameter('domain', description: 'Domain ID.', type: 'integer', example: 1)]
    public function update(UpdateDomainRequest $request, Tenant $tenant, Domain $domain): DomainResource
    {
        $domain = $this->domains->update($tenant, $domain, $request->domainData());

        return (new DomainResource($domain))
            ->withMessage('Domain updated successfully.');
    }

    /**
     * Remove a domain from a tenant.
     *
     * The tenant's last remaining domain cannot be deleted.
     *
     * @operationId deleteTenantDomain
     *
     * @param  Tenant  $tenant  The tenant identified by its UUID primary key.
     * @param  Domain  $domain  The domain row primary key.
     *
     * @response array{
     *     success: true,
     *     message: string,
     *     data: null,
     *     meta: null,
     *     errors: null
     * }
     */
    #[PathParameter('tenant', description: 'Tenant UUID.', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')]
    #[PathParameter('domain', description: 'Domain ID.', type: 'integer', example: 1)]
    public function destroy(Tenant $tenant, Domain $domain): JsonResponse
    {
        $this->authorize('update', $tenant);

        $this->domains->delete($tenant, $domain);

        return ApiResponse::success(message: 'Domain deleted successfully.');
    }
}
