<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexPriceListRequest;
use App\Http\Requests\Tenant\PreviewPriceRequest;
use App\Http\Requests\Tenant\StorePriceListRequest;
use App\Http\Requests\Tenant\UpdatePriceListRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\PriceListResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\PriceList;
use App\Services\Tenant\PriceListService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Price Lists')]
class PriceListController extends Controller
{
    public function __construct(private PriceListService $priceLists) {}

    /**
     * @operationId listPriceLists
     */
    public function index(IndexPriceListRequest $request): ResourceCollection
    {
        return PriceListResource::collection($this->priceLists->list($request->perPage()))
            ->withMessage('Price lists retrieved successfully.');
    }

    /**
     * @operationId createPriceList
     */
    #[DocsResponse(status: 201, description: 'Price list created.', type: 'array{success: true, message: string, data: PriceListResource, meta: null, errors: null}')]
    public function store(StorePriceListRequest $request): JsonResponse
    {
        $list = $this->priceLists->create($request->priceListData());

        return ApiResponse::success(
            data: (new PriceListResource($list))->resolve(),
            message: 'Price list created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showPriceList
     */
    #[PathParameter('price_list', description: 'Price list ID.', type: 'integer', example: 1)]
    public function show(PriceList $priceList): PriceListResource
    {
        $this->authorize('view', $priceList);

        return (new PriceListResource($this->priceLists->find($priceList)))
            ->withMessage('Price list retrieved successfully.');
    }

    /**
     * @operationId updatePriceList
     */
    #[PathParameter('price_list', description: 'Price list ID.', type: 'integer', example: 1)]
    public function update(UpdatePriceListRequest $request, PriceList $priceList): PriceListResource
    {
        return (new PriceListResource($this->priceLists->update($priceList, $request->priceListData())))
            ->withMessage('Price list updated successfully.');
    }

    /**
     * @operationId deletePriceList
     */
    #[PathParameter('price_list', description: 'Price list ID.', type: 'integer', example: 1)]
    public function destroy(PriceList $priceList): JsonResponse
    {
        $this->authorize('delete', $priceList);
        $this->priceLists->delete($priceList);

        return ApiResponse::success(message: 'Price list deleted successfully.');
    }

    /**
     * @operationId previewPrice
     */
    public function preview(PreviewPriceRequest $request): JsonResponse
    {
        return ApiResponse::success(
            data: $this->priceLists->preview($request->previewData()),
            message: 'Price preview generated successfully.',
        );
    }
}
