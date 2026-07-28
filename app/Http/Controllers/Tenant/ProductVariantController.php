<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexProductVariantRequest;
use App\Http\Requests\Tenant\StoreProductOptionRequest;
use App\Http\Requests\Tenant\StoreProductVariantRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\ProductOptionResource;
use App\Http\Resources\Tenant\ProductResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Product;
use App\Services\Tenant\ProductVariantService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Product Variants')]
class ProductVariantController extends Controller
{
    public function __construct(private ProductVariantService $variants) {}

    /**
     * @operationId listProductVariants
     */
    #[PathParameter('product', description: 'Parent product ID.', type: 'integer', example: 1)]
    public function index(IndexProductVariantRequest $request, Product $product): ResourceCollection
    {
        return ProductResource::collection($this->variants->listVariants($product))
            ->withMessage('Product variants retrieved successfully.');
    }

    /**
     * @operationId createProductVariant
     */
    #[PathParameter('product', description: 'Parent product ID.', type: 'integer', example: 1)]
    #[DocsResponse(status: 201, description: 'Product variant created.', type: 'array{success: true, message: string, data: ProductResource, meta: null, errors: null}')]
    public function store(StoreProductVariantRequest $request, Product $product): JsonResponse
    {
        $variant = $this->variants->createVariant($product, $request->variantData());

        return ApiResponse::success(
            data: (new ProductResource($variant))->resolve(),
            message: 'Product variant created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId createProductOption
     */
    #[PathParameter('product', description: 'Product ID.', type: 'integer', example: 1)]
    #[DocsResponse(status: 201, description: 'Product option created.', type: 'array{success: true, message: string, data: ProductOptionResource, meta: null, errors: null}')]
    public function storeOption(StoreProductOptionRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $option = $this->variants->createOption($product, $request->optionData());

        return ApiResponse::success(
            data: (new ProductOptionResource($option))->resolve(),
            message: 'Product option created successfully.',
            status: 201,
        );
    }
}
