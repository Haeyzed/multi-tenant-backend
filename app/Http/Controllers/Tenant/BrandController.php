<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexBrandRequest;
use App\Http\Requests\Tenant\StoreBrandRequest;
use App\Http\Requests\Tenant\UpdateBrandRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\BrandResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Brand;
use App\Services\Tenant\BrandService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Brands')]
class BrandController extends Controller
{
    public function __construct(private BrandService $brands) {}

    /**
     * @operationId listBrands
     */
    public function index(IndexBrandRequest $request): ResourceCollection
    {
        return BrandResource::collection($this->brands->list($request->perPage()))
            ->withMessage('Brands retrieved successfully.');
    }

    /**
     * @operationId createBrand
     */
    #[DocsResponse(status: 201, description: 'Brand created.', type: 'array{success: true, message: string, data: BrandResource, meta: null, errors: null}')]
    public function store(StoreBrandRequest $request): JsonResponse
    {
        $brand = $this->brands->create($request->brandData());

        return ApiResponse::success(
            data: (new BrandResource($brand))->resolve(),
            message: 'Brand created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showBrand
     */
    #[PathParameter('brand', description: 'Brand ID.', type: 'integer', example: 1)]
    public function show(Brand $brand): BrandResource
    {
        $this->authorize('view', $brand);

        return (new BrandResource($this->brands->find($brand)))
            ->withMessage('Brand retrieved successfully.');
    }

    /**
     * @operationId updateBrand
     */
    #[PathParameter('brand', description: 'Brand ID.', type: 'integer', example: 1)]
    public function update(UpdateBrandRequest $request, Brand $brand): BrandResource
    {
        return (new BrandResource($this->brands->update($brand, $request->brandData())))
            ->withMessage('Brand updated successfully.');
    }

    /**
     * @operationId deleteBrand
     */
    #[PathParameter('brand', description: 'Brand ID.', type: 'integer', example: 1)]
    public function destroy(Brand $brand): JsonResponse
    {
        $this->authorize('delete', $brand);
        $this->brands->delete($brand);

        return ApiResponse::success(message: 'Brand deleted successfully.');
    }
}
