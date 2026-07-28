<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Enums\Tenant\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexBusinessSettingRequest;
use App\Http\Requests\Tenant\UpsertBusinessSettingRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\BusinessSettingResource;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\BusinessSettingService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Illuminate\Http\JsonResponse;

#[Group('Business Settings')]
class BusinessSettingController extends Controller
{
    public function __construct(private BusinessSettingService $settings) {}

    /**
     * @operationId listBusinessSettings
     */
    public function index(IndexBusinessSettingRequest $request): ResourceCollection
    {
        return BusinessSettingResource::collection($this->settings->list($request->perPage()))
            ->withMessage('Business settings retrieved successfully.');
    }

    /**
     * @operationId businessSettingsMap
     */
    public function map(): JsonResponse
    {
        abort_unless(request()->user()?->can(Permission::SettingsView->value) ?? false, 403);

        return ApiResponse::success(
            data: $this->settings->map(),
            message: 'Business settings map retrieved successfully.',
        );
    }

    /**
     * @operationId showBusinessSetting
     */
    #[PathParameter('setting', description: 'Setting key.', type: 'string', example: 'company.name')]
    public function show(string $setting): BusinessSettingResource
    {
        abort_unless(request()->user()?->can(Permission::SettingsView->value) ?? false, 403);

        return (new BusinessSettingResource($this->settings->findByKey($setting)))
            ->withMessage('Business setting retrieved successfully.');
    }

    /**
     * @operationId upsertBusinessSetting
     */
    public function upsert(UpsertBusinessSettingRequest $request): BusinessSettingResource
    {
        return (new BusinessSettingResource($this->settings->upsert($request->settingData())))
            ->withMessage('Business setting saved successfully.');
    }

    /**
     * @operationId deleteBusinessSetting
     */
    #[PathParameter('setting', description: 'Setting key.', type: 'string', example: 'company.name')]
    public function destroy(string $setting): JsonResponse
    {
        abort_unless(request()->user()?->can(Permission::SettingsUpdate->value) ?? false, 403);
        $this->settings->delete($this->settings->findByKey($setting));

        return ApiResponse::success(message: 'Business setting deleted successfully.');
    }
}
