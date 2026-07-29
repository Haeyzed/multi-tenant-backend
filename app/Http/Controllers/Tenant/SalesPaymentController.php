<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexSalesPaymentRequest;
use App\Http\Requests\Tenant\StoreSalesPaymentRequest;
use App\Http\Requests\Tenant\UpdateSalesPaymentRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\SalesPaymentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\SalesPayment;
use App\Services\Tenant\SalesPaymentService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Sales Payments')]
class SalesPaymentController extends Controller
{
    public function __construct(private SalesPaymentService $payments) {}

    /**
     * @operationId listSalesPayments
     */
    public function index(IndexSalesPaymentRequest $request): ResourceCollection
    {
        return SalesPaymentResource::collection($this->payments->list($request->perPage()))
            ->withMessage('Sales payments retrieved successfully.');
    }

    /**
     * @operationId createSalesPayment
     */
    #[DocsResponse(status: 201, description: 'Sales payment created.', type: 'array{success: true, message: string, data: SalesPaymentResource, meta: null, errors: null}')]
    public function store(StoreSalesPaymentRequest $request): JsonResponse
    {
        $payment = $this->payments->create($request->paymentData());

        return ApiResponse::success(
            data: (new SalesPaymentResource($payment))->resolve(),
            message: 'Sales payment created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showSalesPayment
     */
    #[PathParameter('sales_payment', description: 'Sales payment ID.', type: 'integer', example: 1)]
    public function show(SalesPayment $salesPayment): SalesPaymentResource
    {
        $this->authorize('view', $salesPayment);

        return (new SalesPaymentResource($this->payments->find($salesPayment)))
            ->withMessage('Sales payment retrieved successfully.');
    }

    /**
     * @operationId updateSalesPayment
     */
    #[PathParameter('sales_payment', description: 'Sales payment ID.', type: 'integer', example: 1)]
    public function update(UpdateSalesPaymentRequest $request, SalesPayment $salesPayment): SalesPaymentResource
    {
        return (new SalesPaymentResource($this->payments->update($salesPayment, $request->paymentData())))
            ->withMessage('Sales payment updated successfully.');
    }

    /**
     * @operationId deleteSalesPayment
     */
    #[PathParameter('sales_payment', description: 'Sales payment ID.', type: 'integer', example: 1)]
    public function destroy(SalesPayment $salesPayment): JsonResponse
    {
        $this->authorize('delete', $salesPayment);
        $this->payments->delete($salesPayment);

        return ApiResponse::success(message: 'Sales payment deleted successfully.');
    }
}
