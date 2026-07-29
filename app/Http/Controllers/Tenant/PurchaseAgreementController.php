<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ActivatePurchaseAgreementRequest;
use App\Http\Requests\Tenant\CancelPurchaseAgreementRequest;
use App\Http\Requests\Tenant\IndexPurchaseAgreementRequest;
use App\Http\Requests\Tenant\StorePurchaseAgreementRequest;
use App\Http\Requests\Tenant\UpdatePurchaseAgreementRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\PurchaseAgreementResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\PurchaseAgreement;
use App\Services\Tenant\PurchaseAgreementService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Purchase Agreements')]
class PurchaseAgreementController extends Controller
{
    public function __construct(private PurchaseAgreementService $agreements) {}

    /**
     * @operationId listPurchaseAgreements
     */
    public function index(IndexPurchaseAgreementRequest $request): ResourceCollection
    {
        return PurchaseAgreementResource::collection($this->agreements->list($request->perPage()))
            ->withMessage('Purchase agreements retrieved successfully.');
    }

    /**
     * @operationId createPurchaseAgreement
     */
    #[DocsResponse(status: 201, description: 'Purchase agreement created.', type: 'array{success: true, message: string, data: PurchaseAgreementResource, meta: null, errors: null}')]
    public function store(StorePurchaseAgreementRequest $request): JsonResponse
    {
        $agreement = $this->agreements->create($request->agreementData());

        return ApiResponse::success(
            data: (new PurchaseAgreementResource($agreement))->resolve(),
            message: 'Purchase agreement created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showPurchaseAgreement
     */
    #[PathParameter('purchase_agreement', description: 'Purchase agreement ID.', type: 'integer', example: 1)]
    public function show(PurchaseAgreement $purchaseAgreement): PurchaseAgreementResource
    {
        $this->authorize('view', $purchaseAgreement);

        return (new PurchaseAgreementResource($this->agreements->find($purchaseAgreement)))
            ->withMessage('Purchase agreement retrieved successfully.');
    }

    /**
     * @operationId updatePurchaseAgreement
     */
    #[PathParameter('purchase_agreement', description: 'Purchase agreement ID.', type: 'integer', example: 1)]
    public function update(UpdatePurchaseAgreementRequest $request, PurchaseAgreement $purchaseAgreement): PurchaseAgreementResource
    {
        return (new PurchaseAgreementResource($this->agreements->update($purchaseAgreement, $request->agreementData())))
            ->withMessage('Purchase agreement updated successfully.');
    }

    /**
     * @operationId deletePurchaseAgreement
     */
    #[PathParameter('purchase_agreement', description: 'Purchase agreement ID.', type: 'integer', example: 1)]
    public function destroy(PurchaseAgreement $purchaseAgreement): JsonResponse
    {
        $this->authorize('delete', $purchaseAgreement);
        $this->agreements->delete($purchaseAgreement);

        return ApiResponse::success(message: 'Purchase agreement deleted successfully.');
    }

    /**
     * @operationId activatePurchaseAgreement
     */
    #[PathParameter('purchase_agreement', description: 'Purchase agreement ID.', type: 'integer', example: 1)]
    public function activate(ActivatePurchaseAgreementRequest $request, PurchaseAgreement $purchaseAgreement): PurchaseAgreementResource
    {
        return (new PurchaseAgreementResource($this->agreements->activate($purchaseAgreement)))
            ->withMessage('Purchase agreement activated successfully.');
    }

    /**
     * @operationId cancelPurchaseAgreement
     */
    #[PathParameter('purchase_agreement', description: 'Purchase agreement ID.', type: 'integer', example: 1)]
    public function cancel(CancelPurchaseAgreementRequest $request, PurchaseAgreement $purchaseAgreement): PurchaseAgreementResource
    {
        return (new PurchaseAgreementResource($this->agreements->cancel($purchaseAgreement)))
            ->withMessage('Purchase agreement cancelled successfully.');
    }
}
