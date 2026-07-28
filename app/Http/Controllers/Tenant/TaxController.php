<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexTaxRequest;
use App\Http\Requests\Tenant\StoreTaxRequest;
use App\Http\Requests\Tenant\UpdateTaxRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\TaxResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Tax;
use App\Services\Tenant\TaxService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Taxes')]
class TaxController extends Controller
{
    public function __construct(private TaxService $taxes) {}

    /**
     * @operationId listTaxes
     */
    public function index(IndexTaxRequest $request): ResourceCollection
    {
        return TaxResource::collection($this->taxes->list($request->perPage()))
            ->withMessage('Taxes retrieved successfully.');
    }

    /**
     * @operationId createTax
     */
    #[DocsResponse(status: 201, description: 'Tax created.', type: 'array{success: true, message: string, data: TaxResource, meta: null, errors: null}')]
    public function store(StoreTaxRequest $request): JsonResponse
    {
        $tax = $this->taxes->create($request->taxData());

        return ApiResponse::success(
            data: (new TaxResource($tax))->resolve(),
            message: 'Tax created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showTax
     */
    #[PathParameter('tax', description: 'Tax ID.', type: 'integer', example: 1)]
    public function show(Tax $tax): TaxResource
    {
        $this->authorize('view', $tax);

        return (new TaxResource($this->taxes->find($tax)))
            ->withMessage('Tax retrieved successfully.');
    }

    /**
     * @operationId updateTax
     */
    #[PathParameter('tax', description: 'Tax ID.', type: 'integer', example: 1)]
    public function update(UpdateTaxRequest $request, Tax $tax): TaxResource
    {
        return (new TaxResource($this->taxes->update($tax, $request->taxData())))
            ->withMessage('Tax updated successfully.');
    }

    /**
     * @operationId deleteTax
     */
    #[PathParameter('tax', description: 'Tax ID.', type: 'integer', example: 1)]
    public function destroy(Tax $tax): JsonResponse
    {
        $this->authorize('delete', $tax);
        $this->taxes->delete($tax);

        return ApiResponse::success(message: 'Tax deleted successfully.');
    }
}
