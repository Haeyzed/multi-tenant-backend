<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexProductRequest;
use App\Http\Requests\Tenant\StoreProductRequest;
use App\Http\Requests\Tenant\UpdateProductRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\ProductResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Product;
use App\Services\Tenant\ProductService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Products')]
class ProductController extends Controller
{
    public function __construct(private ProductService $products) {}

    /**
     * @operationId listProducts
     */
    public function index(IndexProductRequest $request): ResourceCollection
    {
        return ProductResource::collection($this->products->list($request->perPage()))
            ->withMessage('Products retrieved successfully.');
    }

    /**
     * @operationId createProduct
     */
    #[DocsResponse(status: 201, description: 'Product created.', type: 'array{success: true, message: string, data: ProductResource, meta: null, errors: null}')]
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->products->create($request->productData());

        return ApiResponse::success(
            data: (new ProductResource($product))->resolve(),
            message: 'Product created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showProduct
     */
    #[PathParameter('product', description: 'Product ID.', type: 'integer', example: 1)]
    public function show(Product $product): ProductResource
    {
        $this->authorize('view', $product);

        return (new ProductResource($this->products->find($product)))
            ->withMessage('Product retrieved successfully.');
    }

    /**
     * @operationId updateProduct
     */
    #[PathParameter('product', description: 'Product ID.', type: 'integer', example: 1)]
    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        return (new ProductResource($this->products->update($product, $request->productData())))
            ->withMessage('Product updated successfully.');
    }

    /**
     * @operationId deleteProduct
     */
    #[PathParameter('product', description: 'Product ID.', type: 'integer', example: 1)]
    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);
        $this->products->delete($product);

        return ApiResponse::success(message: 'Product deleted successfully.');
    }
}
