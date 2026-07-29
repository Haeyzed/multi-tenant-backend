<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexSupplierReturnRequest;
use App\Http\Requests\Tenant\StoreSupplierReturnRequest;
use App\Http\Requests\Tenant\UpdateSupplierReturnRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\SupplierReturnResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\SupplierReturn;
use App\Services\Tenant\SupplierReturnService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Supplier Returns')]
class SupplierReturnController extends Controller
{
    public function __construct(private SupplierReturnService $supplierReturns) {}

    /**
     * @operationId listSupplierReturns
     */
    public function index(IndexSupplierReturnRequest $request): ResourceCollection
    {
        return SupplierReturnResource::collection($this->supplierReturns->list($request->perPage()))
            ->withMessage('Supplier returns retrieved successfully.');
    }

    /**
     * @operationId createSupplierReturn
     */
    #[DocsResponse(status: 201, description: 'Supplier return created.', type: 'array{success: true, message: string, data: SupplierReturnResource, meta: null, errors: null}')]
    public function store(StoreSupplierReturnRequest $request): JsonResponse
    {
        $supplierReturn = $this->supplierReturns->create($request->supplierReturnData());

        return ApiResponse::success(
            data: (new SupplierReturnResource($supplierReturn))->resolve(),
            message: 'Supplier return created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showSupplierReturn
     */
    #[PathParameter('supplier_return', description: 'Supplier return ID.', type: 'integer', example: 1)]
    public function show(SupplierReturn $supplierReturn): SupplierReturnResource
    {
        $this->authorize('view', $supplierReturn);

        return (new SupplierReturnResource($this->supplierReturns->find($supplierReturn)))
            ->withMessage('Supplier return retrieved successfully.');
    }

    /**
     * @operationId updateSupplierReturn
     */
    #[PathParameter('supplier_return', description: 'Supplier return ID.', type: 'integer', example: 1)]
    public function update(UpdateSupplierReturnRequest $request, SupplierReturn $supplierReturn): SupplierReturnResource
    {
        return (new SupplierReturnResource($this->supplierReturns->update($supplierReturn, $request->supplierReturnData())))
            ->withMessage('Supplier return updated successfully.');
    }

    /**
     * @operationId deleteSupplierReturn
     */
    #[PathParameter('supplier_return', description: 'Supplier return ID.', type: 'integer', example: 1)]
    public function destroy(SupplierReturn $supplierReturn): JsonResponse
    {
        $this->authorize('delete', $supplierReturn);
        $this->supplierReturns->delete($supplierReturn);

        return ApiResponse::success(message: 'Supplier return deleted successfully.');
    }

    /**
     * @operationId postSupplierReturn
     */
    #[PathParameter('supplier_return', description: 'Supplier return ID.', type: 'integer', example: 1)]
    public function post(SupplierReturn $supplierReturn): SupplierReturnResource
    {
        $this->authorize('post', $supplierReturn);

        return (new SupplierReturnResource($this->supplierReturns->post($supplierReturn)))
            ->withMessage('Supplier return posted successfully.');
    }

    /**
     * @operationId cancelSupplierReturn
     */
    #[PathParameter('supplier_return', description: 'Supplier return ID.', type: 'integer', example: 1)]
    public function cancel(SupplierReturn $supplierReturn): SupplierReturnResource
    {
        $this->authorize('cancel', $supplierReturn);

        return (new SupplierReturnResource($this->supplierReturns->cancel($supplierReturn)))
            ->withMessage('Supplier return cancelled successfully.');
    }
}
