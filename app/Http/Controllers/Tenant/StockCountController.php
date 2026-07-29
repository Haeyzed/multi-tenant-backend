<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexStockCountRequest;
use App\Http\Requests\Tenant\StoreStockCountRequest;
use App\Http\Requests\Tenant\UpdateStockCountRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\StockCountResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\StockCount;
use App\Services\Tenant\StockCountService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Stock Counts')]
class StockCountController extends Controller
{
    public function __construct(private StockCountService $stockCounts) {}

    /**
     * @operationId listStockCounts
     */
    public function index(IndexStockCountRequest $request): ResourceCollection
    {
        return StockCountResource::collection($this->stockCounts->list($request->perPage()))
            ->withMessage('Stock counts retrieved successfully.');
    }

    /**
     * @operationId createStockCount
     */
    #[DocsResponse(status: 201, description: 'Stock count created.', type: 'array{success: true, message: string, data: StockCountResource, meta: null, errors: null}')]
    public function store(StoreStockCountRequest $request): JsonResponse
    {
        $stockCount = $this->stockCounts->createDraft($request->stockCountData());

        return ApiResponse::success(
            data: (new StockCountResource($stockCount))->resolve(),
            message: 'Stock count created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showStockCount
     */
    #[PathParameter('stock_count', description: 'Stock count ID.', type: 'integer', example: 1)]
    public function show(StockCount $stockCount): StockCountResource
    {
        $this->authorize('view', $stockCount);

        return (new StockCountResource($this->stockCounts->find($stockCount)))
            ->withMessage('Stock count retrieved successfully.');
    }

    /**
     * @operationId updateStockCount
     */
    #[PathParameter('stock_count', description: 'Stock count ID.', type: 'integer', example: 1)]
    public function update(UpdateStockCountRequest $request, StockCount $stockCount): StockCountResource
    {
        return (new StockCountResource($this->stockCounts->update($stockCount, $request->stockCountData())))
            ->withMessage('Stock count updated successfully.');
    }

    /**
     * @operationId postStockCount
     */
    #[PathParameter('stock_count', description: 'Stock count ID.', type: 'integer', example: 1)]
    public function post(StockCount $stockCount): StockCountResource
    {
        $this->authorize('post', $stockCount);

        return (new StockCountResource($this->stockCounts->post($stockCount)))
            ->withMessage('Stock count posted successfully.');
    }
}
