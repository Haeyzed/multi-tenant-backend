<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexStockAdjustmentReasonRequest;
use App\Http\Requests\Tenant\StoreStockAdjustmentReasonRequest;
use App\Http\Requests\Tenant\UpdateStockAdjustmentReasonRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\StockAdjustmentReasonResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\StockAdjustmentReason;
use App\Services\Tenant\StockAdjustmentReasonService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Stock Adjustment Reasons')]
class StockAdjustmentReasonController extends Controller
{
    public function __construct(private StockAdjustmentReasonService $reasons) {}

    /**
     * @operationId listStockAdjustmentReasons
     */
    public function index(IndexStockAdjustmentReasonRequest $request): ResourceCollection
    {
        return StockAdjustmentReasonResource::collection($this->reasons->list($request->perPage()))
            ->withMessage('Stock adjustment reasons retrieved successfully.');
    }

    /**
     * @operationId createStockAdjustmentReason
     */
    #[DocsResponse(status: 201, description: 'Reason created.', type: 'array{success: true, message: string, data: StockAdjustmentReasonResource, meta: null, errors: null}')]
    public function store(StoreStockAdjustmentReasonRequest $request): JsonResponse
    {
        $reason = $this->reasons->create($request->reasonData());

        return ApiResponse::success(
            data: (new StockAdjustmentReasonResource($reason))->resolve(),
            message: 'Stock adjustment reason created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showStockAdjustmentReason
     */
    #[PathParameter('stock_adjustment_reason', description: 'Stock adjustment reason ID.', type: 'integer', example: 1)]
    public function show(StockAdjustmentReason $stockAdjustmentReason): StockAdjustmentReasonResource
    {
        $this->authorize('view', $stockAdjustmentReason);

        return (new StockAdjustmentReasonResource($this->reasons->find($stockAdjustmentReason)))
            ->withMessage('Stock adjustment reason retrieved successfully.');
    }

    /**
     * @operationId updateStockAdjustmentReason
     */
    #[PathParameter('stock_adjustment_reason', description: 'Stock adjustment reason ID.', type: 'integer', example: 1)]
    public function update(
        UpdateStockAdjustmentReasonRequest $request,
        StockAdjustmentReason $stockAdjustmentReason,
    ): StockAdjustmentReasonResource {
        return (new StockAdjustmentReasonResource($this->reasons->update($stockAdjustmentReason, $request->reasonData())))
            ->withMessage('Stock adjustment reason updated successfully.');
    }

    /**
     * @operationId deleteStockAdjustmentReason
     */
    #[PathParameter('stock_adjustment_reason', description: 'Stock adjustment reason ID.', type: 'integer', example: 1)]
    public function destroy(StockAdjustmentReason $stockAdjustmentReason): JsonResponse
    {
        $this->authorize('delete', $stockAdjustmentReason);
        $this->reasons->delete($stockAdjustmentReason);

        return ApiResponse::success(message: 'Stock adjustment reason deleted successfully.');
    }
}
