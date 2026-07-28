<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant;
use App\Services\Central\TenantOpsService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Illuminate\Http\JsonResponse;

#[Group('Tenant Support')]
class TenantOpsController extends Controller
{
    public function __construct(private TenantOpsService $ops) {}

    /**
     * Support operational snapshot for a tenant.
     *
     * @operationId tenantOpsSummary
     */
    #[PathParameter('tenant', description: 'Tenant UUID.', type: 'string', format: 'uuid')]
    public function show(Tenant $tenant): JsonResponse
    {
        $this->authorize('view', $tenant);

        return ApiResponse::success(
            data: $this->ops->summary($tenant),
            message: 'Tenant ops summary retrieved successfully.',
        );
    }
}
