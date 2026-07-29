<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexChannelInventoryRequest;
use App\Http\Requests\Tenant\UpsertChannelInventoryRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\ChannelInventoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Channel;
use App\Models\Tenant\ChannelInventory;
use App\Services\Tenant\ChannelInventoryService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Channel Inventories')]
class ChannelInventoryController extends Controller
{
    public function __construct(private ChannelInventoryService $inventories) {}

    /**
     * @operationId listChannelInventories
     */
    #[PathParameter('channel', description: 'Channel ID.', type: 'integer', example: 1)]
    public function index(IndexChannelInventoryRequest $request, Channel $channel): ResourceCollection
    {
        return ChannelInventoryResource::collection($this->inventories->list($channel, $request->perPage()))
            ->withMessage('Channel inventories retrieved successfully.');
    }

    /**
     * @operationId upsertChannelInventory
     */
    #[PathParameter('channel', description: 'Channel ID.', type: 'integer', example: 1)]
    #[DocsResponse(status: 201, description: 'Channel inventory upserted.', type: 'array{success: true, message: string, data: ChannelInventoryResource, meta: null, errors: null}')]
    public function store(UpsertChannelInventoryRequest $request, Channel $channel): JsonResponse
    {
        $inventory = $this->inventories->upsert($channel, $request->inventoryData());

        return ApiResponse::success(
            data: (new ChannelInventoryResource($inventory))->resolve(),
            message: 'Channel inventory upserted successfully.',
            status: 201,
        );
    }

    /**
     * @operationId deleteChannelInventory
     */
    #[PathParameter('channel', description: 'Channel ID.', type: 'integer', example: 1)]
    #[PathParameter('channel_inventory', description: 'Channel inventory ID.', type: 'integer', example: 1)]
    public function destroy(Channel $channel, ChannelInventory $channelInventory): JsonResponse
    {
        abort_unless($channelInventory->channel_id === $channel->id, 404);

        $this->authorize('update', $channel);
        $this->inventories->delete($channelInventory);

        return ApiResponse::success(message: 'Channel inventory deleted successfully.');
    }
}
