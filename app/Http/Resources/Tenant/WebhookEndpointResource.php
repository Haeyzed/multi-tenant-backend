<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\WebhookEndpoint;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read WebhookEndpoint $resource
 *
 * @mixin WebhookEndpoint
 */
#[SchemaName('WebhookEndpoint')]
class WebhookEndpointResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'events' => $this->events,
            'is_active' => $this->is_active,
            'deliveries_count' => $this->when(isset($this->deliveries_count), $this->deliveries_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
