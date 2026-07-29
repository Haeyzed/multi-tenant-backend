<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexChannelRequest;
use App\Http\Requests\Tenant\PublishChannelProductRequest;
use App\Http\Requests\Tenant\StoreChannelRequest;
use App\Http\Requests\Tenant\UpdateChannelRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\ChannelResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Channel;
use App\Models\Tenant\Product;
use App\Services\Tenant\ChannelService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Channels')]
class ChannelController extends Controller
{
    public function __construct(private ChannelService $channels) {}

    /**
     * @operationId listChannels
     */
    public function index(IndexChannelRequest $request): ResourceCollection
    {
        return ChannelResource::collection($this->channels->list($request->perPage()))
            ->withMessage('Channels retrieved successfully.');
    }

    /**
     * @operationId createChannel
     */
    #[DocsResponse(status: 201, description: 'Channel created.', type: 'array{success: true, message: string, data: ChannelResource, meta: null, errors: null}')]
    public function store(StoreChannelRequest $request): JsonResponse
    {
        $channel = $this->channels->create($request->channelData());

        return ApiResponse::success(
            data: (new ChannelResource($channel))->resolve(),
            message: 'Channel created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showChannel
     */
    #[PathParameter('channel', description: 'Channel ID.', type: 'integer', example: 1)]
    public function show(Channel $channel): ChannelResource
    {
        $this->authorize('view', $channel);

        return (new ChannelResource($this->channels->find($channel)))
            ->withMessage('Channel retrieved successfully.');
    }

    /**
     * @operationId updateChannel
     */
    #[PathParameter('channel', description: 'Channel ID.', type: 'integer', example: 1)]
    public function update(UpdateChannelRequest $request, Channel $channel): ChannelResource
    {
        return (new ChannelResource($this->channels->update($channel, $request->channelData())))
            ->withMessage('Channel updated successfully.');
    }

    /**
     * @operationId deleteChannel
     */
    #[PathParameter('channel', description: 'Channel ID.', type: 'integer', example: 1)]
    public function destroy(Channel $channel): JsonResponse
    {
        $this->authorize('delete', $channel);
        $this->channels->delete($channel);

        return ApiResponse::success(message: 'Channel deleted successfully.');
    }

    /**
     * @operationId syncChannelInventory
     */
    #[PathParameter('channel', description: 'Channel ID.', type: 'integer', example: 1)]
    public function syncInventory(Channel $channel): JsonResponse
    {
        $this->authorize('update', $channel);

        return ApiResponse::success(
            data: $this->channels->syncInventory($channel),
            message: 'Channel inventory synced successfully.',
        );
    }

    /**
     * @operationId publishChannelProduct
     */
    #[PathParameter('channel', description: 'Channel ID.', type: 'integer', example: 1)]
    public function publishProduct(PublishChannelProductRequest $request, Channel $channel): ChannelResource
    {
        /** @var Product $product */
        $product = Product::query()->findOrFail($request->productId());

        return (new ChannelResource($this->channels->publishProduct($channel, $product)))
            ->withMessage('Product published to channel successfully.');
    }
}
