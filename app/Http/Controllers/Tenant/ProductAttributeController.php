<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AssignProductAttributesRequest;
use App\Http\Resources\Tenant\ProductAttributeValueResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Product;
use App\Services\Tenant\AttributeService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Illuminate\Http\JsonResponse;

#[Group('Product Attributes')]
class ProductAttributeController extends Controller
{
    public function __construct(private AttributeService $attributes) {}

    /**
     * @operationId assignProductAttributes
     */
    #[PathParameter('product', description: 'Product ID.', type: 'integer', example: 1)]
    public function update(AssignProductAttributesRequest $request, Product $product): JsonResponse
    {
        $assigned = $this->attributes->assignToProduct($product, $request->assignments());

        return ApiResponse::success(
            data: ProductAttributeValueResource::collection(collect($assigned))->resolve(),
            message: 'Product attributes assigned successfully.',
        );
    }
}
