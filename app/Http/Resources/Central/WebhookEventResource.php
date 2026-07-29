<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Http\Resources\Resource;
use App\Models\Central\WebhookEvent;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read WebhookEvent $resource
 *
 * @mixin WebhookEvent
 */
#[SchemaName('WebhookEvent')]
class WebhookEventResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gateway' => $this->gateway->value,
            'event_id' => $this->event_id,
            'type' => $this->type,
            'status' => $this->status->value,
            'error' => $this->error,
            'payload' => $this->payload,
            'processed_at' => $this->processed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
