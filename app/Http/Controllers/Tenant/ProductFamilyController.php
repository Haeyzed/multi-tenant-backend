<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\ProductFamilyResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\ProductFamily;
use App\Services\Tenant\ProductFamilyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductFamilyController extends Controller
{
    public function __construct(private ProductFamilyService $productFamilies) {}

    /** @operationId listProductFamilies */
    public function index(Request $request): ResourceCollection
    {
        $this->authorize('viewAny', ProductFamily::class);

        return ProductFamilyResource::collection($this->productFamilies->list((int) $request->integer('per_page', 15)));
    }

    /** @operationId createProductFamily */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ProductFamily::class);
        $productFamily = $this->productFamilies->create($request->validate(['name' => ['required', 'string', 'max:255'], 'code' => ['sometimes', 'string', 'max:255', 'unique:product_families,code'], 'description' => ['nullable', 'string'], 'is_active' => ['sometimes', 'boolean']]));

        return ApiResponse::success(data: (new ProductFamilyResource($productFamily))->resolve(), message: 'Product family created successfully.', status: 201);
    }

    /** @operationId showProductFamily */
    public function show(ProductFamily $productFamily): ProductFamilyResource
    {
        $this->authorize('view', $productFamily);

        return new ProductFamilyResource($this->productFamilies->find($productFamily));
    }

    /** @operationId updateProductFamily */
    public function update(Request $request, ProductFamily $productFamily): ProductFamilyResource
    {
        $this->authorize('update', $productFamily);

        return new ProductFamilyResource($this->productFamilies->update($productFamily, $request->validate(['name' => ['sometimes', 'string', 'max:255'], 'code' => ['sometimes', 'string', 'max:255', 'unique:product_families,code,'.$productFamily->id], 'description' => ['nullable', 'string'], 'is_active' => ['sometimes', 'boolean']])));
    }

    /** @operationId deleteProductFamily */
    public function destroy(ProductFamily $productFamily): JsonResponse
    {
        $this->authorize('delete', $productFamily);
        $this->productFamilies->delete($productFamily);

        return ApiResponse::success(message: 'Product family deleted successfully.');
    }
}
