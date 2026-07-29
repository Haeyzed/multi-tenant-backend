<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexProductRelationRequest;
use App\Http\Requests\Tenant\StoreProductRelationRequest;
use App\Http\Requests\Tenant\UpdateProductRelationRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\ProductRelationResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductRelation;
use App\Services\Tenant\ProductRelationService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Product Relations')]
class ProductRelationController extends Controller
{
    public function __construct(private ProductRelationService $relations) {}

    /**
     * @operationId listProductRelations
     */
    #[PathParameter('product', description: 'Product ID.', type: 'integer', example: 1)]
    public function index(IndexProductRelationRequest $request, Product $product): ResourceCollection
    {
        return ProductRelationResource::collection($this->relations->list($product, $request->perPage()))
            ->withMessage('Product relations retrieved successfully.');
    }

    /**
     * @operationId createProductRelation
     */
    #[PathParameter('product', description: 'Product ID.', type: 'integer', example: 1)]
    #[DocsResponse(status: 201, description: 'Product relation created.', type: 'array{success: true, message: string, data: ProductRelationResource, meta: null, errors: null}')]
    public function store(StoreProductRelationRequest $request, Product $product): JsonResponse
    {
        $relation = $this->relations->create($product, $request->relationData());

        return ApiResponse::success(
            data: (new ProductRelationResource($relation))->resolve(),
            message: 'Product relation created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showProductRelation
     */
    #[PathParameter('product', description: 'Product ID.', type: 'integer', example: 1)]
    #[PathParameter('relation', description: 'Product relation ID.', type: 'integer', example: 1)]
    public function show(Product $product, ProductRelation $relation): ProductRelationResource
    {
        abort_unless($relation->product_id === $product->id, 404);
        $this->authorize('view', $relation);

        return (new ProductRelationResource($this->relations->find($relation)))
            ->withMessage('Product relation retrieved successfully.');
    }

    /**
     * @operationId updateProductRelation
     */
    #[PathParameter('product', description: 'Product ID.', type: 'integer', example: 1)]
    #[PathParameter('relation', description: 'Product relation ID.', type: 'integer', example: 1)]
    public function update(UpdateProductRelationRequest $request, Product $product, ProductRelation $relation): ProductRelationResource
    {
        abort_unless($relation->product_id === $product->id, 404);

        return (new ProductRelationResource($this->relations->update($relation, $request->relationData())))
            ->withMessage('Product relation updated successfully.');
    }

    /**
     * @operationId deleteProductRelation
     */
    #[PathParameter('product', description: 'Product ID.', type: 'integer', example: 1)]
    #[PathParameter('relation', description: 'Product relation ID.', type: 'integer', example: 1)]
    public function destroy(Product $product, ProductRelation $relation): JsonResponse
    {
        abort_unless($relation->product_id === $product->id, 404);
        $this->authorize('delete', $relation);
        $this->relations->delete($relation);

        return ApiResponse::success(message: 'Product relation deleted successfully.');
    }
}
