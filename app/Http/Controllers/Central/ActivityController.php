<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Enums\Central\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\IndexActivityRequest;
use App\Http\Resources\Central\ActivityResource;
use App\Http\Resources\ResourceCollection;
use App\Models\Central\Activity;
use App\Services\Central\ActivityLogService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;

#[Group('Activity')]
class ActivityController extends Controller
{
    public function __construct(private ActivityLogService $activities) {}

    /**
     * @operationId listActivity
     */
    public function index(IndexActivityRequest $request): ResourceCollection
    {
        return ActivityResource::collection($this->activities->list($request->perPage()))
            ->withMessage('Activity log retrieved successfully.');
    }

    /**
     * @operationId showActivity
     */
    #[PathParameter('activity', description: 'Activity ID.', type: 'integer', example: 1)]
    public function show(Activity $activity): ActivityResource
    {
        abort_unless(request()->user()?->can(Permission::ActivityView->value) ?? false, 403);

        return (new ActivityResource($this->activities->find($activity)))
            ->withMessage('Activity entry retrieved successfully.');
    }
}
