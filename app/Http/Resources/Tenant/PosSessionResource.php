<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\PosSession;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read PosSession $resource
 *
 * @mixin PosSession
 */
#[SchemaName('PosSession')]
class PosSessionResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'channel_id' => $this->channel_id,
            'opened_by' => $this->opened_by,
            'closed_by' => $this->closed_by,
            'status' => $this->status->value,
            'opening_float' => $this->opening_float,
            'closing_float' => $this->closing_float,
            'notes' => $this->notes,
            'opened_at' => $this->opened_at,
            'closed_at' => $this->closed_at,
            'channel' => new ChannelResource($this->whenLoaded('channel')),
            'orders_count' => $this->when(isset($this->orders_count), $this->orders_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
