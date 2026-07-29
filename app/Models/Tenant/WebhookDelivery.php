<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\WebhookDeliveryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $webhook_endpoint_id
 * @property string $event
 * @property array<string, mixed> $payload
 * @property WebhookDeliveryStatus $status
 * @property int $attempts
 * @property int|null $response_code
 * @property string|null $response_body
 * @property Carbon|null $delivered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'webhook_endpoint_id',
    'event',
    'payload',
    'status',
    'attempts',
    'response_code',
    'response_body',
    'delivered_at',
])]
class WebhookDelivery extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => WebhookDeliveryStatus::class,
            'attempts' => 'integer',
            'response_code' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WebhookEndpoint, $this>
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
