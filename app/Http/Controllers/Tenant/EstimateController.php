<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\EstimateResource;
use App\Http\Resources\Tenant\QuotationResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Estimate;
use App\Services\Tenant\EstimateService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Estimates')]
class EstimateController extends Controller
{
    public function __construct(private EstimateService $estimates) {}

    /**
     * @operationId listEstimates
     */
    public function index(Request $request): ResourceCollection
    {
        $this->authorize('viewAny', Estimate::class);

        return EstimateResource::collection($this->estimates->list((int) $request->integer('per_page', 15)))
            ->withMessage('Estimates retrieved successfully.');
    }

    /**
     * @operationId createEstimate
     */
    #[DocsResponse(status: 201, description: 'Estimate created.', type: 'array{success: true, message: string, data: EstimateResource, meta: null, errors: null}')]
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Estimate::class);

        $estimate = $this->estimates->create($request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'tax_id' => ['nullable', 'integer', 'exists:taxes,id'],
            'notes' => ['nullable', 'string'],
            'valid_until' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]));

        return ApiResponse::success(
            data: (new EstimateResource($estimate))->resolve(),
            message: 'Estimate created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showEstimate
     */
    #[PathParameter('estimate', description: 'Estimate ID.', type: 'integer', example: 1)]
    public function show(Estimate $estimate): EstimateResource
    {
        $this->authorize('view', $estimate);

        return (new EstimateResource($this->estimates->find($estimate)))
            ->withMessage('Estimate retrieved successfully.');
    }

    /**
     * @operationId updateEstimate
     */
    #[PathParameter('estimate', description: 'Estimate ID.', type: 'integer', example: 1)]
    public function update(Request $request, Estimate $estimate): EstimateResource
    {
        $this->authorize('update', $estimate);

        return (new EstimateResource($this->estimates->update($estimate, $request->validate([
            'tax_id' => ['nullable', 'integer', 'exists:taxes,id'],
            'notes' => ['nullable', 'string'],
            'valid_until' => ['nullable', 'date'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]))))->withMessage('Estimate updated successfully.');
    }

    /**
     * @operationId deleteEstimate
     */
    #[PathParameter('estimate', description: 'Estimate ID.', type: 'integer', example: 1)]
    public function destroy(Estimate $estimate): JsonResponse
    {
        $this->authorize('delete', $estimate);
        $this->estimates->delete($estimate);

        return ApiResponse::success(message: 'Estimate deleted successfully.');
    }

    /**
     * @operationId sendEstimate
     */
    #[PathParameter('estimate', description: 'Estimate ID.', type: 'integer', example: 1)]
    public function send(Estimate $estimate): EstimateResource
    {
        $this->authorize('update', $estimate);

        return (new EstimateResource($this->estimates->send($estimate)))
            ->withMessage('Estimate sent successfully.');
    }

    /**
     * @operationId convertEstimateToQuotation
     */
    #[PathParameter('estimate', description: 'Estimate ID.', type: 'integer', example: 1)]
    public function convertToQuotation(Estimate $estimate): JsonResponse
    {
        $this->authorize('update', $estimate);

        $quotation = $this->estimates->convertToQuotation($estimate);

        return ApiResponse::success(
            data: (new QuotationResource($quotation))->resolve(),
            message: 'Estimate converted to quotation successfully.',
            status: 201,
        );
    }
}
