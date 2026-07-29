<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexActivityRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\ActivityResource;
use App\Models\Tenant\Activity;
use App\Services\Tenant\ActivityService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;

#[Group('Activity')]
class ActivityController extends Controller
{
    public function __construct(private ActivityService $activities) {}

    /**
     * @operationId listActivity
     */
    public function index(IndexActivityRequest $request): ResourceCollection
    {
        return ActivityResource::collection($this->activities->list($request->perPage()))
            ->withMessage('Activity retrieved successfully.');
    }

    /**
     * @operationId showActivity
     */
    #[PathParameter('activity', description: 'Activity ID.', type: 'integer', example: 1)]
    public function show(Activity $activity): ActivityResource
    {
        $this->authorize('view', $activity);

        return (new ActivityResource($this->activities->find($activity)))
            ->withMessage('Activity retrieved successfully.');
    }
}
