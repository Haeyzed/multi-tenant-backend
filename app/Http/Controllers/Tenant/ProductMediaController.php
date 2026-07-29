<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexProductMediaRequest;
use App\Http\Requests\Tenant\StoreProductMediaRequest;
use App\Http\Requests\Tenant\UpdateProductMediaRequest;
use App\Http\Requests\Tenant\UploadProductMediaRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\ProductMediaResource;
use App\Http\Resources\Tenant\UploadedProductMediaResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductMedia;
use App\Services\Tenant\ProductMediaService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

#[Group('Product Media')]
class ProductMediaController extends Controller
{
    public function __construct(private ProductMediaService $media) {}

    /**
     * @operationId listProductMedia
     */
    #[PathParameter('product', description: 'Product ID.', type: 'integer', example: 1)]
    public function index(IndexProductMediaRequest $request, Product $product): ResourceCollection
    {
        return ProductMediaResource::collection($this->media->list($product, $request->perPage()))
            ->withMessage('Product media retrieved successfully.');
    }

    /**
     * @operationId uploadProductMedia
     */
    #[PathParameter('product', description: 'Product ID.', type: 'integer', example: 1)]
    #[DocsResponse(status: 201, description: 'Product media uploaded.', type: 'array{success: true, message: string, data: UploadedProductMediaResource, meta: null, errors: null}')]
    public function upload(UploadProductMediaRequest $request, Product $product): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');

        $media = $this->media->upload(
            $product,
            $file,
            $request->collectionName(),
        );

        return ApiResponse::success(
            data: (new UploadedProductMediaResource($media))->resolve(),
            message: 'Product media uploaded successfully.',
            status: 201,
        );
    }

    /**
     * @operationId createProductMedia
     */
    #[PathParameter('product', description: 'Product ID.', type: 'integer', example: 1)]
    #[DocsResponse(status: 201, description: 'Product media created.', type: 'array{success: true, message: string, data: ProductMediaResource, meta: null, errors: null}')]
    public function store(StoreProductMediaRequest $request, Product $product): JsonResponse
    {
        $media = $this->media->create($product, $request->mediaData());

        return ApiResponse::success(
            data: (new ProductMediaResource($media))->resolve(),
            message: 'Product media created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showProductMedia
     */
    #[PathParameter('product', description: 'Product ID.', type: 'integer', example: 1)]
    #[PathParameter('medium', description: 'Product media ID.', type: 'integer', example: 1)]
    public function show(Product $product, ProductMedia $medium): ProductMediaResource
    {
        abort_unless($medium->product_id === $product->id, 404);
        $this->authorize('view', $medium);

        return (new ProductMediaResource($this->media->find($medium)))
            ->withMessage('Product media retrieved successfully.');
    }

    /**
     * @operationId updateProductMedia
     */
    #[PathParameter('product', description: 'Product ID.', type: 'integer', example: 1)]
    #[PathParameter('medium', description: 'Product media ID.', type: 'integer', example: 1)]
    public function update(UpdateProductMediaRequest $request, Product $product, ProductMedia $medium): ProductMediaResource
    {
        abort_unless($medium->product_id === $product->id, 404);

        return (new ProductMediaResource($this->media->update($medium, $request->mediaData())))
            ->withMessage('Product media updated successfully.');
    }

    /**
     * @operationId deleteProductMedia
     */
    #[PathParameter('product', description: 'Product ID.', type: 'integer', example: 1)]
    #[PathParameter('medium', description: 'Product media ID.', type: 'integer', example: 1)]
    public function destroy(Product $product, ProductMedia $medium): JsonResponse
    {
        abort_unless($medium->product_id === $product->id, 404);
        $this->authorize('delete', $medium);
        $this->media->delete($medium);

        return ApiResponse::success(message: 'Product media deleted successfully.');
    }
}
