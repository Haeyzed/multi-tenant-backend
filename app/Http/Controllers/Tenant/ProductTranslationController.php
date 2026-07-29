<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpsertProductTranslationRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\ProductTranslationResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Product;
use App\Services\Tenant\ProductTranslationService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Product Translations')]
class ProductTranslationController extends Controller
{
    public function __construct(private ProductTranslationService $translations) {}

    /**
     * @operationId listProductTranslations
     */
    #[PathParameter('product', description: 'Product ID.', type: 'integer', example: 1)]
    public function index(Product $product): ResourceCollection
    {
        $this->authorize('view', $product);

        return ProductTranslationResource::collection($this->translations->list($product))
            ->withMessage('Product translations retrieved successfully.');
    }

    /**
     * @operationId upsertProductTranslation
     */
    #[PathParameter('product', description: 'Product ID.', type: 'integer', example: 1)]
    #[PathParameter('locale', description: 'Locale code.', type: 'string', example: 'fr')]
    #[DocsResponse(status: 200, description: 'Product translation upserted.', type: 'array{success: true, message: string, data: ProductTranslationResource, meta: null, errors: null}')]
    public function upsert(UpsertProductTranslationRequest $request, Product $product, string $locale): JsonResponse
    {
        $translation = $this->translations->upsert($product, $locale, $request->translationData());

        return ApiResponse::success(
            data: (new ProductTranslationResource($translation))->resolve(),
            message: 'Product translation saved successfully.',
        );
    }

    /**
     * @operationId deleteProductTranslation
     */
    #[PathParameter('product', description: 'Product ID.', type: 'integer', example: 1)]
    #[PathParameter('locale', description: 'Locale code.', type: 'string', example: 'fr')]
    public function destroy(Product $product, string $locale): JsonResponse
    {
        $this->authorize('update', $product);
        $this->translations->delete($product, $locale);

        return ApiResponse::success(message: 'Product translation deleted successfully.');
    }
}
