<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexStockLotRequest;
use App\Http\Requests\Tenant\StoreStockLotRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\StockLotResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\StockLot;
use App\Services\Tenant\LotService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Stock Lots')]
class StockLotController extends Controller
{
    public function __construct(private LotService $lots) {}

    /**
     * @operationId listStockLots
     */
    public function index(IndexStockLotRequest $request): ResourceCollection
    {
        return StockLotResource::collection($this->lots->list($request->perPage()))
            ->withMessage('Stock lots retrieved successfully.');
    }

    /**
     * @operationId receiveStockLot
     */
    #[DocsResponse(status: 201, description: 'Stock lot received.', type: 'array{success: true, message: string, data: StockLotResource, meta: null, errors: null}')]
    public function store(StoreStockLotRequest $request): JsonResponse
    {
        $stockLot = $this->lots->receiveLot($request->lotData());

        return ApiResponse::success(
            data: (new StockLotResource($stockLot))->resolve(),
            message: 'Stock lot received successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showStockLot
     */
    #[PathParameter('stock_lot', description: 'Stock lot ID.', type: 'integer', example: 1)]
    public function show(StockLot $stockLot): StockLotResource
    {
        $this->authorize('view', $stockLot);

        return (new StockLotResource($this->lots->find($stockLot)))
            ->withMessage('Stock lot retrieved successfully.');
    }
}
