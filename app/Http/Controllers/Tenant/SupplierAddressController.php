<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreSupplierAddressRequest;
use App\Http\Requests\Tenant\UpdateSupplierAddressRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\SupplierAddressResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\SupplierAddress;
use App\Services\Tenant\SupplierCrmService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Supplier Addresses')]
class SupplierAddressController extends Controller
{
    public function __construct(private SupplierCrmService $crm) {}

    /**
     * @operationId listSupplierAddresses
     */
    #[PathParameter('supplier', description: 'Supplier ID.', type: 'integer', example: 1)]
    public function index(Request $request, Supplier $supplier): ResourceCollection
    {
        $this->authorize('view', $supplier);

        return SupplierAddressResource::collection(
            $this->crm->listAddresses($supplier, (int) $request->integer('per_page', 15))
        )->withMessage('Supplier addresses retrieved successfully.');
    }

    /**
     * @operationId createSupplierAddress
     */
    #[PathParameter('supplier', description: 'Supplier ID.', type: 'integer', example: 1)]
    #[DocsResponse(status: 201, description: 'Address created.', type: 'array{success: true, message: string, data: SupplierAddressResource, meta: null, errors: null}')]
    public function store(StoreSupplierAddressRequest $request, Supplier $supplier): JsonResponse
    {
        $address = $this->crm->createAddress($supplier, $request->validated());

        return ApiResponse::success(
            data: (new SupplierAddressResource($address))->resolve(),
            message: 'Supplier address created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showSupplierAddress
     */
    #[PathParameter('supplier', description: 'Supplier ID.', type: 'integer', example: 1)]
    #[PathParameter('address', description: 'Address ID.', type: 'integer', example: 1)]
    public function show(Supplier $supplier, SupplierAddress $address): SupplierAddressResource
    {
        abort_unless($address->supplier_id === $supplier->id, 404);
        $this->authorize('view', $supplier);

        return (new SupplierAddressResource($address))
            ->withMessage('Supplier address retrieved successfully.');
    }

    /**
     * @operationId updateSupplierAddress
     */
    #[PathParameter('supplier', description: 'Supplier ID.', type: 'integer', example: 1)]
    #[PathParameter('address', description: 'Address ID.', type: 'integer', example: 1)]
    public function update(
        UpdateSupplierAddressRequest $request,
        Supplier $supplier,
        SupplierAddress $address,
    ): SupplierAddressResource {
        abort_unless($address->supplier_id === $supplier->id, 404);

        return (new SupplierAddressResource($this->crm->updateAddress($supplier, $address, $request->validated())))
            ->withMessage('Supplier address updated successfully.');
    }

    /**
     * @operationId deleteSupplierAddress
     */
    #[PathParameter('supplier', description: 'Supplier ID.', type: 'integer', example: 1)]
    #[PathParameter('address', description: 'Address ID.', type: 'integer', example: 1)]
    public function destroy(Supplier $supplier, SupplierAddress $address): JsonResponse
    {
        abort_unless($address->supplier_id === $supplier->id, 404);
        $this->authorize('update', $supplier);
        $this->crm->deleteAddress($supplier, $address);

        return ApiResponse::success(message: 'Supplier address deleted successfully.');
    }
}
