<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Enums\Billing\FeatureFlagKey;
use App\Enums\Central\Permission;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Central\FeatureFlagService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Feature Flags')]
class FeatureFlagController extends Controller
{
    public function __construct(private FeatureFlagService $flags) {}

    /**
     * @operationId listFeatureFlags
     */
    public function index(): JsonResponse
    {
        abort_unless(request()->user()?->can(Permission::SettingsView->value) ?? false, 403);

        return ApiResponse::success(
            data: array_values($this->flags->all()),
            message: 'Feature flags retrieved successfully.',
        );
    }

    /**
     * @operationId upsertFeatureFlag
     */
    public function upsert(Request $request): JsonResponse
    {
        abort_unless(request()->user()?->can(Permission::SettingsUpdate->value) ?? false, 403);

        $validated = $request->validate([
            'key' => ['required', 'string', Rule::in(array_map(
                static fn (FeatureFlagKey $flag): string => $flag->value,
                FeatureFlagKey::all(),
            ))],
            'enabled' => ['required', 'boolean'],
        ]);

        $this->flags->set($validated['key'], (bool) $validated['enabled']);

        return ApiResponse::success(
            data: $this->flags->all()[$validated['key']],
            message: 'Feature flag saved successfully.',
        );
    }
}
