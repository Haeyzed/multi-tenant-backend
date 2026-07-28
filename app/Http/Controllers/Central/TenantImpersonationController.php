<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant;
use App\Services\Central\TenantImpersonationService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Tenant Support')]
class TenantImpersonationController extends Controller
{
    public function __construct(private TenantImpersonationService $impersonation) {}

    /**
     * Issue a short-lived tenant API token for support impersonation.
     *
     * @operationId impersonateTenant
     */
    #[PathParameter('tenant', description: 'Tenant UUID.', type: 'string', format: 'uuid')]
    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('impersonate', $tenant);

        $validated = $request->validate([
            'user_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'minutes' => ['sometimes', 'integer', 'min:1', 'max:480'],
        ]);

        return ApiResponse::success(
            data: $this->impersonation->impersonate(
                tenant: $tenant,
                actor: $request->user(),
                tenantUserId: isset($validated['user_id']) ? (int) $validated['user_id'] : null,
                minutes: (int) ($validated['minutes'] ?? 60),
            ),
            message: 'Impersonation token issued successfully.',
            status: 201,
        );
    }
}
