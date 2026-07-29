<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexGoodsReceiptRequest;
use App\Http\Requests\Tenant\StoreGoodsReceiptRequest;
use App\Http\Requests\Tenant\UpdateGoodsReceiptRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\GoodsReceiptResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\GoodsReceipt;
use App\Services\Tenant\GoodsReceiptService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Goods Receipts')]
class GoodsReceiptController extends Controller
{
    public function __construct(private GoodsReceiptService $goodsReceipts) {}

    /**
     * @operationId listGoodsReceipts
     */
    public function index(IndexGoodsReceiptRequest $request): ResourceCollection
    {
        return GoodsReceiptResource::collection($this->goodsReceipts->list($request->perPage()))
            ->withMessage('Goods receipts retrieved successfully.');
    }

    /**
     * @operationId createGoodsReceipt
     */
    #[DocsResponse(status: 201, description: 'Goods receipt created.', type: 'array{success: true, message: string, data: GoodsReceiptResource, meta: null, errors: null}')]
    public function store(StoreGoodsReceiptRequest $request): JsonResponse
    {
        $goodsReceipt = $this->goodsReceipts->create($request->goodsReceiptData());

        return ApiResponse::success(
            data: (new GoodsReceiptResource($goodsReceipt))->resolve(),
            message: 'Goods receipt created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showGoodsReceipt
     */
    #[PathParameter('goods_receipt', description: 'Goods receipt ID.', type: 'integer', example: 1)]
    public function show(GoodsReceipt $goodsReceipt): GoodsReceiptResource
    {
        $this->authorize('view', $goodsReceipt);

        return (new GoodsReceiptResource($this->goodsReceipts->find($goodsReceipt)))
            ->withMessage('Goods receipt retrieved successfully.');
    }

    /**
     * @operationId updateGoodsReceipt
     */
    #[PathParameter('goods_receipt', description: 'Goods receipt ID.', type: 'integer', example: 1)]
    public function update(UpdateGoodsReceiptRequest $request, GoodsReceipt $goodsReceipt): GoodsReceiptResource
    {
        return (new GoodsReceiptResource($this->goodsReceipts->update($goodsReceipt, $request->goodsReceiptData())))
            ->withMessage('Goods receipt updated successfully.');
    }

    /**
     * @operationId deleteGoodsReceipt
     */
    #[PathParameter('goods_receipt', description: 'Goods receipt ID.', type: 'integer', example: 1)]
    public function destroy(GoodsReceipt $goodsReceipt): JsonResponse
    {
        $this->authorize('delete', $goodsReceipt);
        $this->goodsReceipts->delete($goodsReceipt);

        return ApiResponse::success(message: 'Goods receipt deleted successfully.');
    }

    /**
     * @operationId postGoodsReceipt
     */
    #[PathParameter('goods_receipt', description: 'Goods receipt ID.', type: 'integer', example: 1)]
    public function post(GoodsReceipt $goodsReceipt): GoodsReceiptResource
    {
        $this->authorize('post', $goodsReceipt);

        return (new GoodsReceiptResource($this->goodsReceipts->post($goodsReceipt)))
            ->withMessage('Goods receipt posted successfully.');
    }

    /**
     * @operationId cancelGoodsReceipt
     */
    #[PathParameter('goods_receipt', description: 'Goods receipt ID.', type: 'integer', example: 1)]
    public function cancel(GoodsReceipt $goodsReceipt): GoodsReceiptResource
    {
        $this->authorize('cancel', $goodsReceipt);

        return (new GoodsReceiptResource($this->goodsReceipts->cancel($goodsReceipt)))
            ->withMessage('Goods receipt cancelled successfully.');
    }
}
