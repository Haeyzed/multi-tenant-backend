<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexChannelProductPriceRequest;
use App\Http\Requests\Tenant\UpsertChannelProductPriceRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\ChannelProductPriceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Channel;
use App\Models\Tenant\ChannelProductPrice;
use App\Services\Tenant\ChannelPricingService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Channel Prices')]
class ChannelProductPriceController extends Controller
{
    public function __construct(private ChannelPricingService $prices) {}

    /**
     * @operationId listChannelPrices
     */
    #[PathParameter('channel', description: 'Channel ID.', type: 'integer', example: 1)]
    public function index(IndexChannelProductPriceRequest $request, Channel $channel): ResourceCollection
    {
        return ChannelProductPriceResource::collection($this->prices->list($channel, $request->perPage()))
            ->withMessage('Channel prices retrieved successfully.');
    }

    /**
     * @operationId upsertChannelPrice
     */
    #[PathParameter('channel', description: 'Channel ID.', type: 'integer', example: 1)]
    #[DocsResponse(status: 201, description: 'Channel price upserted.', type: 'array{success: true, message: string, data: ChannelProductPriceResource, meta: null, errors: null}')]
    public function store(UpsertChannelProductPriceRequest $request, Channel $channel): JsonResponse
    {
        $price = $this->prices->upsert($channel, $request->priceData());

        return ApiResponse::success(
            data: (new ChannelProductPriceResource($price))->resolve(),
            message: 'Channel price upserted successfully.',
            status: 201,
        );
    }

    /**
     * @operationId deleteChannelPrice
     */
    #[PathParameter('channel', description: 'Channel ID.', type: 'integer', example: 1)]
    #[PathParameter('channel_product_price', description: 'Channel product price ID.', type: 'integer', example: 1)]
    public function destroy(Channel $channel, ChannelProductPrice $channelProductPrice): JsonResponse
    {
        abort_unless($channelProductPrice->channel_id === $channel->id, 404);

        $this->authorize('update', $channel);
        $this->prices->delete($channelProductPrice);

        return ApiResponse::success(message: 'Channel price deleted successfully.');
    }
}
