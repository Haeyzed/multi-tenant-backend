<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Enums\Central\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\IndexPlatformSettingRequest;
use App\Http\Requests\Central\UpsertPlatformSettingRequest;
use App\Http\Resources\Central\PlatformSettingResource;
use App\Http\Resources\ResourceCollection;
use App\Http\Responses\ApiResponse;
use App\Services\Central\PlatformSettingService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Platform Settings')]
class PlatformSettingController extends Controller
{
    public function __construct(private PlatformSettingService $settings) {}

    /**
     * @operationId listPlatformSettings
     */
    public function index(IndexPlatformSettingRequest $request): ResourceCollection
    {
        return PlatformSettingResource::collection($this->settings->list($request->perPage()))
            ->withMessage('Platform settings retrieved successfully.');
    }

    /**
     * @operationId showPlatformSetting
     */
    #[PathParameter('setting', description: 'Setting key.', type: 'string', example: 'support.email')]
    public function show(string $setting): PlatformSettingResource
    {
        abort_unless(request()->user()?->can(Permission::SettingsView->value) ?? false, 403);

        return (new PlatformSettingResource($this->settings->findByKey($setting)))
            ->withMessage('Platform setting retrieved successfully.');
    }

    /**
     * @operationId upsertPlatformSetting
     */
    #[DocsResponse(status: 200, description: 'Setting upserted.', type: 'array{success: true, message: string, data: PlatformSettingResource, meta: null, errors: null}')]
    public function upsert(UpsertPlatformSettingRequest $request): PlatformSettingResource
    {
        return (new PlatformSettingResource($this->settings->upsert($request->settingData())))
            ->withMessage('Platform setting saved successfully.');
    }

    /**
     * @operationId deletePlatformSetting
     */
    #[PathParameter('setting', description: 'Setting key.', type: 'string', example: 'support.email')]
    public function destroy(string $setting): JsonResponse
    {
        abort_unless(request()->user()?->can(Permission::SettingsUpdate->value) ?? false, 403);
        $this->settings->delete($this->settings->findByKey($setting));

        return ApiResponse::success(message: 'Platform setting deleted successfully.');
    }
}
