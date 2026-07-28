<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Billing\BillingGateway;
use App\Enums\Billing\WebhookEventStatus;
use Database\Factories\WebhookEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Idempotent store for inbound billing gateway webhooks.
 *
 * @property int $id
 * @property BillingGateway $gateway
 * @property string $event_id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property WebhookEventStatus $status
 * @property string|null $error
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'gateway',
    'event_id',
    'type',
    'payload',
    'status',
    'error',
    'processed_at',
])]
class WebhookEvent extends Model
{
    /** @use HasFactory<WebhookEventFactory> */
    use CentralConnection, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gateway' => BillingGateway::class,
            'payload' => 'array',
            'status' => WebhookEventStatus::class,
            'processed_at' => 'datetime',
        ];
    }
}
