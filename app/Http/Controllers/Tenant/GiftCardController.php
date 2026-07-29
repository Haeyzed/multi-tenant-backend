<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CheckGiftCardBalanceRequest;
use App\Http\Requests\Tenant\IndexGiftCardRequest;
use App\Http\Requests\Tenant\RedeemGiftCardRequest;
use App\Http\Requests\Tenant\StoreGiftCardRequest;
use App\Http\Requests\Tenant\VoidGiftCardRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\GiftCardRedemptionResource;
use App\Http\Resources\Tenant\GiftCardResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\GiftCard;
use App\Models\Tenant\Order;
use App\Services\Tenant\GiftCardService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Gift Cards')]
class GiftCardController extends Controller
{
    public function __construct(private GiftCardService $giftCards) {}

    /**
     * @operationId listGiftCards
     */
    public function index(IndexGiftCardRequest $request): ResourceCollection
    {
        return GiftCardResource::collection($this->giftCards->list($request->perPage()))
            ->withMessage('Gift cards retrieved successfully.');
    }

    /**
     * @operationId createGiftCard
     */
    #[DocsResponse(status: 201, description: 'Gift card issued.', type: 'array{success: true, message: string, data: GiftCardResource, meta: null, errors: null}')]
    public function store(StoreGiftCardRequest $request): JsonResponse
    {
        $giftCard = $this->giftCards->issue($request->giftCardData());

        return ApiResponse::success(
            data: (new GiftCardResource($giftCard))->resolve(),
            message: 'Gift card issued successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showGiftCard
     */
    #[PathParameter('gift_card', description: 'Gift card ID.', type: 'integer', example: 1)]
    public function show(GiftCard $giftCard): GiftCardResource
    {
        $this->authorize('view', $giftCard);

        return (new GiftCardResource($this->giftCards->find($giftCard)))
            ->withMessage('Gift card retrieved successfully.');
    }

    /**
     * @operationId deleteGiftCard
     */
    #[PathParameter('gift_card', description: 'Gift card ID.', type: 'integer', example: 1)]
    public function destroy(GiftCard $giftCard): JsonResponse
    {
        $this->authorize('delete', $giftCard);
        $this->giftCards->delete($giftCard);

        return ApiResponse::success(message: 'Gift card deleted successfully.');
    }

    /**
     * @operationId checkGiftCardBalance
     */
    public function checkBalance(CheckGiftCardBalanceRequest $request): GiftCardResource
    {
        return (new GiftCardResource($this->giftCards->checkBalance($request->validated('code'))))
            ->withMessage('Gift card balance retrieved successfully.');
    }

    /**
     * @operationId voidGiftCard
     */
    #[PathParameter('gift_card', description: 'Gift card ID.', type: 'integer', example: 1)]
    public function void(VoidGiftCardRequest $request, GiftCard $giftCard): GiftCardResource
    {
        return (new GiftCardResource($this->giftCards->void($giftCard)))
            ->withMessage('Gift card voided successfully.');
    }

    /**
     * @operationId redeemGiftCardOnOrder
     */
    #[PathParameter('order', description: 'Order ID.', type: 'integer', example: 1)]
    public function redeemOnOrder(RedeemGiftCardRequest $request, Order $order): GiftCardRedemptionResource
    {
        $this->authorize('view', $order);

        $redemption = $this->giftCards->redeem(
            $request->validated('code'),
            (int) $request->validated('amount'),
            $order,
        );

        return (new GiftCardRedemptionResource($redemption))
            ->withMessage('Gift card redeemed successfully.');
    }
}
