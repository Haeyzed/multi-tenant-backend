<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexSupplierPaymentRequest;
use App\Http\Requests\Tenant\StoreSupplierPaymentRequest;
use App\Http\Requests\Tenant\UpdateSupplierPaymentRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\SupplierPaymentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\SupplierPayment;
use App\Services\Tenant\SupplierPaymentService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Supplier Payments')]
class SupplierPaymentController extends Controller
{
    public function __construct(private SupplierPaymentService $payments) {}

    /**
     * @operationId listSupplierPayments
     */
    public function index(IndexSupplierPaymentRequest $request): ResourceCollection
    {
        return SupplierPaymentResource::collection($this->payments->list($request->perPage()))
            ->withMessage('Supplier payments retrieved successfully.');
    }

    /**
     * @operationId createSupplierPayment
     */
    #[DocsResponse(status: 201, description: 'Supplier payment created.', type: 'array{success: true, message: string, data: SupplierPaymentResource, meta: null, errors: null}')]
    public function store(StoreSupplierPaymentRequest $request): JsonResponse
    {
        $payment = $this->payments->create($request->paymentData());

        return ApiResponse::success(
            data: (new SupplierPaymentResource($payment))->resolve(),
            message: 'Supplier payment created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showSupplierPayment
     */
    #[PathParameter('supplier_payment', description: 'Supplier payment ID.', type: 'integer', example: 1)]
    public function show(SupplierPayment $supplierPayment): SupplierPaymentResource
    {
        $this->authorize('view', $supplierPayment);

        return (new SupplierPaymentResource($this->payments->find($supplierPayment)))
            ->withMessage('Supplier payment retrieved successfully.');
    }

    /**
     * @operationId updateSupplierPayment
     */
    #[PathParameter('supplier_payment', description: 'Supplier payment ID.', type: 'integer', example: 1)]
    public function update(UpdateSupplierPaymentRequest $request, SupplierPayment $supplierPayment): SupplierPaymentResource
    {
        return (new SupplierPaymentResource($this->payments->update($supplierPayment, $request->paymentData())))
            ->withMessage('Supplier payment updated successfully.');
    }

    /**
     * @operationId deleteSupplierPayment
     */
    #[PathParameter('supplier_payment', description: 'Supplier payment ID.', type: 'integer', example: 1)]
    public function destroy(SupplierPayment $supplierPayment): JsonResponse
    {
        $this->authorize('delete', $supplierPayment);
        $this->payments->delete($supplierPayment);

        return ApiResponse::success(message: 'Supplier payment deleted successfully.');
    }
}
