<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexStockSerialRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\StockSerialResource;
use App\Models\Tenant\StockSerial;
use App\Services\Tenant\StockSerialService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;

#[Group('Stock Serials')]
class StockSerialController extends Controller
{
    public function __construct(private StockSerialService $serials) {}

    /**
     * @operationId listStockSerials
     */
    public function index(IndexStockSerialRequest $request): ResourceCollection
    {
        return StockSerialResource::collection($this->serials->list($request->perPage()))
            ->withMessage('Stock serials retrieved successfully.');
    }

    /**
     * @operationId showStockSerial
     */
    #[PathParameter('stock_serial', description: 'Stock serial ID.', type: 'integer', example: 1)]
    public function show(StockSerial $stockSerial): StockSerialResource
    {
        $this->authorize('view', $stockSerial);

        return (new StockSerialResource($this->serials->find($stockSerial)))
            ->withMessage('Stock serial retrieved successfully.');
    }
}
