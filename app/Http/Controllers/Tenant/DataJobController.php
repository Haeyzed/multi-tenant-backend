<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexDataJobRequest;
use App\Http\Requests\Tenant\StoreDataJobRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\DataJobResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\DataJob;
use App\Services\Tenant\DataJobService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Data Jobs')]
class DataJobController extends Controller
{
    public function __construct(private DataJobService $dataJobs) {}

    /**
     * @operationId listDataJobs
     */
    public function index(IndexDataJobRequest $request): ResourceCollection
    {
        return DataJobResource::collection($this->dataJobs->list($request->perPage()))
            ->withMessage('Data jobs retrieved successfully.');
    }

    /**
     * @operationId createDataJob
     */
    #[DocsResponse(status: 201, description: 'Data job created.', type: 'array{success: true, message: string, data: DataJobResource, meta: null, errors: null}')]
    public function store(StoreDataJobRequest $request): JsonResponse
    {
        $dataJob = $this->dataJobs->create($request->dataJobData());

        return ApiResponse::success(
            data: (new DataJobResource($dataJob))->resolve(),
            message: 'Data job created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showDataJob
     */
    #[PathParameter('data_job', description: 'Data job ID.', type: 'integer', example: 1)]
    public function show(DataJob $dataJob): DataJobResource
    {
        $this->authorize('view', $dataJob);

        return (new DataJobResource($this->dataJobs->find($dataJob)))
            ->withMessage('Data job retrieved successfully.');
    }

    /**
     * @operationId deleteDataJob
     */
    #[PathParameter('data_job', description: 'Data job ID.', type: 'integer', example: 1)]
    public function destroy(DataJob $dataJob): JsonResponse
    {
        $this->authorize('delete', $dataJob);
        $this->dataJobs->delete($dataJob);

        return ApiResponse::success(message: 'Data job deleted successfully.');
    }

    /**
     * @operationId cancelDataJob
     */
    #[PathParameter('data_job', description: 'Data job ID.', type: 'integer', example: 1)]
    public function cancel(DataJob $dataJob): DataJobResource
    {
        $this->authorize('cancel', $dataJob);

        return (new DataJobResource($this->dataJobs->cancel($dataJob)))
            ->withMessage('Data job cancelled successfully.');
    }
}
