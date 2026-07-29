<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AcceptQuotationRequest;
use App\Http\Requests\Tenant\IndexQuotationRequest;
use App\Http\Requests\Tenant\StoreQuotationRequest;
use App\Http\Requests\Tenant\UpdateQuotationRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\QuotationResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Quotation;
use App\Services\Tenant\QuotationService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Quotations')]
class QuotationController extends Controller
{
    public function __construct(private QuotationService $quotations) {}

    /**
     * @operationId listQuotations
     */
    public function index(IndexQuotationRequest $request): ResourceCollection
    {
        return QuotationResource::collection($this->quotations->list($request->perPage()))
            ->withMessage('Quotations retrieved successfully.');
    }

    /**
     * @operationId createQuotation
     */
    #[DocsResponse(status: 201, description: 'Quotation created.', type: 'array{success: true, message: string, data: QuotationResource, meta: null, errors: null}')]
    public function store(StoreQuotationRequest $request): JsonResponse
    {
        $quotation = $this->quotations->create($request->quotationData());

        return ApiResponse::success(
            data: (new QuotationResource($quotation))->resolve(),
            message: 'Quotation created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showQuotation
     */
    #[PathParameter('quotation', description: 'Quotation ID.', type: 'integer', example: 1)]
    public function show(Quotation $quotation): QuotationResource
    {
        $this->authorize('view', $quotation);

        return (new QuotationResource($this->quotations->find($quotation)))
            ->withMessage('Quotation retrieved successfully.');
    }

    /**
     * @operationId updateQuotation
     */
    #[PathParameter('quotation', description: 'Quotation ID.', type: 'integer', example: 1)]
    public function update(UpdateQuotationRequest $request, Quotation $quotation): QuotationResource
    {
        return (new QuotationResource($this->quotations->update($quotation, $request->quotationData())))
            ->withMessage('Quotation updated successfully.');
    }

    /**
     * @operationId deleteQuotation
     */
    #[PathParameter('quotation', description: 'Quotation ID.', type: 'integer', example: 1)]
    public function destroy(Quotation $quotation): JsonResponse
    {
        $this->authorize('delete', $quotation);
        $this->quotations->delete($quotation);

        return ApiResponse::success(message: 'Quotation deleted successfully.');
    }

    /**
     * @operationId sendQuotation
     */
    #[PathParameter('quotation', description: 'Quotation ID.', type: 'integer', example: 1)]
    public function send(Quotation $quotation): QuotationResource
    {
        $this->authorize('send', $quotation);

        return (new QuotationResource($this->quotations->send($quotation)))
            ->withMessage('Quotation sent successfully.');
    }

    /**
     * @operationId acceptQuotation
     */
    #[PathParameter('quotation', description: 'Quotation ID.', type: 'integer', example: 1)]
    public function accept(AcceptQuotationRequest $request, Quotation $quotation): QuotationResource
    {
        return (new QuotationResource($this->quotations->accept($quotation, $request->acceptData())))
            ->withMessage('Quotation accepted and order created successfully.');
    }

    /**
     * @operationId rejectQuotation
     */
    #[PathParameter('quotation', description: 'Quotation ID.', type: 'integer', example: 1)]
    public function reject(Quotation $quotation): QuotationResource
    {
        $this->authorize('reject', $quotation);

        return (new QuotationResource($this->quotations->reject($quotation)))
            ->withMessage('Quotation rejected successfully.');
    }
}
