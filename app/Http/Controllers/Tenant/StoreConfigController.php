<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Enums\Tenant\Permission;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\StoreConfigService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Store Configuration')]
class StoreConfigController extends Controller
{
    public function __construct(private StoreConfigService $storeConfig) {}

    /**
     * @operationId showStoreConfig
     */
    public function show(): JsonResponse
    {
        abort_unless(request()->user()?->can(Permission::SettingsView->value) ?? false, 403);

        return ApiResponse::success(
            data: $this->storeConfig->get()->toArray(),
            message: 'Store configuration retrieved successfully.',
        );
    }

    /**
     * @operationId updateStoreConfig
     */
    public function update(Request $request): JsonResponse
    {
        abort_unless(request()->user()?->can(Permission::SettingsUpdate->value) ?? false, 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'timezone' => ['sometimes', 'nullable', 'string', 'timezone', 'max:64'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:16'],
            'tax_inclusive' => ['sometimes', 'nullable', 'boolean'],
            'default_tax_id' => ['sometimes', 'nullable', 'integer', Rule::exists('taxes', 'id')],
            'logo_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        return ApiResponse::success(
            data: $this->storeConfig->update($validated)->toArray(),
            message: 'Store configuration updated successfully.',
        );
    }
}
