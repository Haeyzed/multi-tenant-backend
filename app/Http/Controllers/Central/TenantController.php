<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\IndexTenantRequest;
use App\Http\Requests\Central\StoreTenantRequest;
use App\Http\Requests\Central\UpdateTenantRequest;
use App\Http\Resources\Central\TenantResource;
use App\Http\Resources\ResourceCollection;
use App\Http\Responses\ApiResponse;
use App\Models\Central\Tenant;
use App\Services\Central\TenantService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

/**
 * Central tenant provisioning and management.
 *
 * Creates, lists, updates, and deletes tenants (including domain and database provisioning).
 */
#[Group('Tenants')]
class TenantController extends Controller
{
    public function __construct(private TenantService $tenants) {}

    /**
     * List tenants.
     *
     * Returns a paginated collection of tenants. Supports Spatie Query Builder
     * filters, sorts, and includes via query parameters.
     *
     * @operationId listTenants
     */
    public function index(IndexTenantRequest $request): ResourceCollection
    {
        $tenants = $this->tenants->list($request->perPage());

        return TenantResource::collection($tenants)
            ->withMessage('Tenants retrieved successfully.');
    }

    /**
     * Provision a tenant.
     *
     * Creates the central tenant record, primary domain, and tenant database, then
     * runs tenant migrations and seeders.
     *
     * @operationId createTenant
     */
    #[DocsResponse(
        status: 201,
        description: 'Tenant provisioned successfully.',
        type: 'array{success: true, message: string, data: TenantResource, meta: null, errors: null}',
    )]
    public function store(StoreTenantRequest $request): JsonResponse
    {
        $tenant = $this->tenants->create($request->tenantData());

        return ApiResponse::success(
            data: (new TenantResource($tenant))->resolve(),
            message: 'Tenant provisioned successfully.',
            status: 201,
        );
    }

    /**
     * Show a tenant.
     *
     * Returns a single tenant with loaded domains.
     *
     * @operationId showTenant
     *
     * @param  Tenant  $tenant  The tenant identified by its UUID primary key.
     */
    #[PathParameter('tenant', description: 'Tenant UUID.', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')]
    public function show(Tenant $tenant): TenantResource
    {
        $this->authorize('view', $tenant);

        return (new TenantResource($this->tenants->find($tenant)))
            ->withMessage('Tenant retrieved successfully.');
    }

    /**
     * Update a tenant.
     *
     * Updates mutable tenant attributes such as the display name.
     *
     * @operationId updateTenant
     *
     * @param  Tenant  $tenant  The tenant identified by its UUID primary key.
     */
    #[PathParameter('tenant', description: 'Tenant UUID.', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')]
    public function update(UpdateTenantRequest $request, Tenant $tenant): TenantResource
    {
        $tenant = $this->tenants->update($tenant, $request->tenantData());

        return (new TenantResource($tenant))
            ->withMessage('Tenant updated successfully.');
    }

    /**
     * Delete a tenant.
     *
     * Removes the tenant record and tears down its database and domains.
     *
     * @operationId deleteTenant
     *
     * @param  Tenant  $tenant  The tenant identified by its UUID primary key.
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
    public function destroy(Tenant $tenant): JsonResponse
    {
        $this->authorize('delete', $tenant);

        $this->tenants->delete($tenant);

        return ApiResponse::success(message: 'Tenant deleted successfully.');
    }
}
