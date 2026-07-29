<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AttachProductUomRequest;
use App\Http\Requests\Tenant\IndexUnitOfMeasureRequest;
use App\Http\Requests\Tenant\StoreUnitOfMeasureRequest;
use App\Http\Requests\Tenant\UpdateProductUomRequest;
use App\Http\Requests\Tenant\UpdateUnitOfMeasureRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\ProductUomResource;
use App\Http\Resources\Tenant\UnitOfMeasureResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductUom;
use App\Models\Tenant\UnitOfMeasure;
use App\Services\Tenant\UnitOfMeasureService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Units of Measure')]
class UnitOfMeasureController extends Controller
{
    public function __construct(private UnitOfMeasureService $unitsOfMeasure) {}

    /**
     * @operationId listUnitsOfMeasure
     */
    public function index(IndexUnitOfMeasureRequest $request): ResourceCollection
    {
        return UnitOfMeasureResource::collection($this->unitsOfMeasure->list($request->perPage()))
            ->withMessage('Units of measure retrieved successfully.');
    }

    /**
     * @operationId createUnitOfMeasure
     */
    #[DocsResponse(status: 201, description: 'Unit of measure created.', type: 'array{success: true, message: string, data: UnitOfMeasureResource, meta: null, errors: null}')]
    public function store(StoreUnitOfMeasureRequest $request): JsonResponse
    {
        $unitOfMeasure = $this->unitsOfMeasure->create($request->unitOfMeasureData());

        return ApiResponse::success(
            data: (new UnitOfMeasureResource($unitOfMeasure))->resolve(),
            message: 'Unit of measure created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showUnitOfMeasure
     */
    #[PathParameter('unit_of_measure', description: 'Unit of measure ID.', type: 'integer', example: 1)]
    public function show(UnitOfMeasure $unitOfMeasure): UnitOfMeasureResource
    {
        $this->authorize('view', $unitOfMeasure);

        return (new UnitOfMeasureResource($this->unitsOfMeasure->find($unitOfMeasure)))
            ->withMessage('Unit of measure retrieved successfully.');
    }

    /**
     * @operationId updateUnitOfMeasure
     */
    #[PathParameter('unit_of_measure', description: 'Unit of measure ID.', type: 'integer', example: 1)]
    public function update(UpdateUnitOfMeasureRequest $request, UnitOfMeasure $unitOfMeasure): UnitOfMeasureResource
    {
        return (new UnitOfMeasureResource($this->unitsOfMeasure->update($unitOfMeasure, $request->unitOfMeasureData())))
            ->withMessage('Unit of measure updated successfully.');
    }

    /**
     * @operationId deleteUnitOfMeasure
     */
    #[PathParameter('unit_of_measure', description: 'Unit of measure ID.', type: 'integer', example: 1)]
    public function destroy(UnitOfMeasure $unitOfMeasure): JsonResponse
    {
        $this->authorize('delete', $unitOfMeasure);
        $this->unitsOfMeasure->delete($unitOfMeasure);

        return ApiResponse::success(message: 'Unit of measure deleted successfully.');
    }

    /**
     * @operationId listProductUoms
     */
    #[PathParameter('product', description: 'Product ID.', type: 'integer', example: 1)]
    public function indexProductUoms(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        return ApiResponse::success(
            data: ProductUomResource::collection($this->unitsOfMeasure->listProductUoms($product))->resolve(),
            message: 'Product units of measure retrieved successfully.',
        );
    }

    /**
     * @operationId attachProductUom
     */
    #[PathParameter('product', description: 'Product ID.', type: 'integer', example: 1)]
    #[DocsResponse(status: 201, description: 'Product unit of measure attached.', type: 'array{success: true, message: string, data: ProductUomResource, meta: null, errors: null}')]
    public function attachProductUom(AttachProductUomRequest $request, Product $product): JsonResponse
    {
        $productUom = $this->unitsOfMeasure->attachProductUom($product, $request->productUomData());

        return ApiResponse::success(
            data: (new ProductUomResource($productUom))->resolve(),
            message: 'Product unit of measure attached successfully.',
            status: 201,
        );
    }

    /**
     * @operationId updateProductUom
     */
    #[PathParameter('product', description: 'Product ID.', type: 'integer', example: 1)]
    #[PathParameter('product_uom', description: 'Product UoM ID.', type: 'integer', example: 1)]
    public function updateProductUom(UpdateProductUomRequest $request, Product $product, ProductUom $productUom): JsonResponse
    {
        abort_unless($productUom->product_id === $product->id, 404);

        $productUom = $this->unitsOfMeasure->updateProductUom($productUom, $request->productUomData());

        return ApiResponse::success(
            data: (new ProductUomResource($productUom))->resolve(),
            message: 'Product unit of measure updated successfully.',
        );
    }

    /**
     * @operationId detachProductUom
     */
    #[PathParameter('product', description: 'Product ID.', type: 'integer', example: 1)]
    #[PathParameter('product_uom', description: 'Product UoM ID.', type: 'integer', example: 1)]
    public function detachProductUom(Product $product, ProductUom $productUom): JsonResponse
    {
        abort_unless($productUom->product_id === $product->id, 404);
        $this->authorize('update', $product);
        $this->unitsOfMeasure->detachProductUom($productUom);

        return ApiResponse::success(message: 'Product unit of measure detached successfully.');
    }
}
