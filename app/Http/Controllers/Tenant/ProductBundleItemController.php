<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SyncProductBundleItemsRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\ProductBundleItemResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Product;
use App\Services\Tenant\BundleService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Illuminate\Http\JsonResponse;

#[Group('Product Bundles')]
class ProductBundleItemController extends Controller
{
    public function __construct(private BundleService $bundles) {}

    /**
     * @operationId listProductBundleItems
     */
    #[PathParameter('product', description: 'Bundle product ID.', type: 'integer', example: 1)]
    public function index(Product $product): ResourceCollection
    {
        $this->authorize('view', $product);

        return ProductBundleItemResource::collection($this->bundles->components($product))
            ->withMessage('Bundle components retrieved successfully.');
    }

    /**
     * @operationId syncProductBundleItems
     */
    #[PathParameter('product', description: 'Bundle product ID.', type: 'integer', example: 1)]
    public function sync(SyncProductBundleItemsRequest $request, Product $product): JsonResponse
    {
        $items = $this->bundles->syncComponents($product, $request->validated('items'));

        return ApiResponse::success(
            data: ProductBundleItemResource::collection($items)->resolve(),
            message: 'Bundle components synced successfully.',
        );
    }
}
