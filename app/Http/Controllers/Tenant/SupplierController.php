<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexSupplierRequest;
use App\Http\Requests\Tenant\StoreSupplierRequest;
use App\Http\Requests\Tenant\UpdateSupplierRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\SupplierResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Supplier;
use App\Services\Tenant\SupplierService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Suppliers')]
class SupplierController extends Controller
{
    public function __construct(private SupplierService $suppliers) {}

    /**
     * @operationId listSuppliers
     */
    public function index(IndexSupplierRequest $request): ResourceCollection
    {
        return SupplierResource::collection($this->suppliers->list($request->perPage()))
            ->withMessage('Suppliers retrieved successfully.');
    }

    /**
     * @operationId createSupplier
     */
    #[DocsResponse(status: 201, description: 'Supplier created.', type: 'array{success: true, message: string, data: SupplierResource, meta: null, errors: null}')]
    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = $this->suppliers->create($request->supplierData());

        return ApiResponse::success(
            data: (new SupplierResource($supplier))->resolve(),
            message: 'Supplier created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showSupplier
     */
    #[PathParameter('supplier', description: 'Supplier ID.', type: 'integer', example: 1)]
    public function show(Supplier $supplier): SupplierResource
    {
        $this->authorize('view', $supplier);

        return (new SupplierResource($this->suppliers->find($supplier)))
            ->withMessage('Supplier retrieved successfully.');
    }

    /**
     * @operationId updateSupplier
     */
    #[PathParameter('supplier', description: 'Supplier ID.', type: 'integer', example: 1)]
    public function update(UpdateSupplierRequest $request, Supplier $supplier): SupplierResource
    {
        return (new SupplierResource($this->suppliers->update($supplier, $request->supplierData())))
            ->withMessage('Supplier updated successfully.');
    }

    /**
     * @operationId deleteSupplier
     */
    #[PathParameter('supplier', description: 'Supplier ID.', type: 'integer', example: 1)]
    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->authorize('delete', $supplier);
        $this->suppliers->delete($supplier);

        return ApiResponse::success(message: 'Supplier deleted successfully.');
    }
}
