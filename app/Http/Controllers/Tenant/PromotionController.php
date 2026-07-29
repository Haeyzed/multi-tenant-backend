<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexPromotionRequest;
use App\Http\Requests\Tenant\StorePromotionRequest;
use App\Http\Requests\Tenant\UpdatePromotionRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\PromotionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Promotion;
use App\Services\Tenant\PromotionService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Promotions')]
class PromotionController extends Controller
{
    public function __construct(private PromotionService $promotions) {}

    /**
     * @operationId listPromotions
     */
    public function index(IndexPromotionRequest $request): ResourceCollection
    {
        return PromotionResource::collection($this->promotions->list($request->perPage()))
            ->withMessage('Promotions retrieved successfully.');
    }

    /**
     * @operationId createPromotion
     */
    #[DocsResponse(status: 201, description: 'Promotion created.', type: 'array{success: true, message: string, data: PromotionResource, meta: null, errors: null}')]
    public function store(StorePromotionRequest $request): JsonResponse
    {
        $promotion = $this->promotions->create($request->promotionData());

        return ApiResponse::success(
            data: (new PromotionResource($promotion))->resolve(),
            message: 'Promotion created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showPromotion
     */
    #[PathParameter('promotion', description: 'Promotion ID.', type: 'integer', example: 1)]
    public function show(Promotion $promotion): PromotionResource
    {
        $this->authorize('view', $promotion);

        return (new PromotionResource($this->promotions->find($promotion)))
            ->withMessage('Promotion retrieved successfully.');
    }

    /**
     * @operationId updatePromotion
     */
    #[PathParameter('promotion', description: 'Promotion ID.', type: 'integer', example: 1)]
    public function update(UpdatePromotionRequest $request, Promotion $promotion): PromotionResource
    {
        return (new PromotionResource($this->promotions->update($promotion, $request->promotionData())))
            ->withMessage('Promotion updated successfully.');
    }

    /**
     * @operationId deletePromotion
     */
    #[PathParameter('promotion', description: 'Promotion ID.', type: 'integer', example: 1)]
    public function destroy(Promotion $promotion): JsonResponse
    {
        $this->authorize('delete', $promotion);
        $this->promotions->delete($promotion);

        return ApiResponse::success(message: 'Promotion deleted successfully.');
    }
}
