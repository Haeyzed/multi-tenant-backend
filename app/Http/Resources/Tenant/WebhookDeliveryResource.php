<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\WebhookDelivery;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read WebhookDelivery $resource
 *
 * @mixin WebhookDelivery
 */
#[SchemaName('WebhookDelivery')]
class WebhookDeliveryResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'webhook_endpoint_id' => $this->webhook_endpoint_id,
            'event' => $this->event,
            'payload' => $this->payload,
            'status' => $this->status->value,
            'attempts' => $this->attempts,
            'response_code' => $this->response_code,
            'response_body' => $this->response_body,
            'delivered_at' => $this->delivered_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
