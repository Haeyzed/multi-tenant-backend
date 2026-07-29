<?php

declare(strict_types=1);

namespace App\Jobs\Tenant;

use App\Enums\Tenant\WebhookDeliveryStatus;
use App\Models\Tenant\WebhookDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class DeliverWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        /** @var WebhookDelivery|null $delivery */
        $delivery = WebhookDelivery::query()->with('endpoint')->find($this->deliveryId);

        if ($delivery === null || $delivery->endpoint === null) {
            return;
        }

        $body = [
            'event' => $delivery->event,
            'payload' => $delivery->payload,
            'delivered_at' => now()->toIso8601String(),
        ];

        $json = json_encode($body, JSON_THROW_ON_ERROR);
        $headers = [
            'Content-Type' => 'application/json',
            'X-Webhook-Event' => $delivery->event,
        ];

        if ($delivery->endpoint->secret) {
            $headers['X-Webhook-Signature'] = hash_hmac('sha256', $json, $delivery->endpoint->secret);
        }

        try {
            $response = Http::timeout(10)
                ->connectTimeout(3)
                ->withHeaders($headers)
                ->withBody($json, 'application/json')
                ->post($delivery->endpoint->url);

            $delivery->update([
                'attempts' => $delivery->attempts + 1,
                'response_code' => $response->status(),
                'response_body' => Str::limit($response->body(), 2000),
                'status' => $response->successful()
                    ? WebhookDeliveryStatus::Delivered
                    : WebhookDeliveryStatus::Failed,
                'delivered_at' => $response->successful() ? now() : null,
            ]);

            if (! $response->successful()) {
                $response->throw();
            }
        } catch (Throwable $e) {
            $delivery->update([
                'attempts' => $delivery->attempts + 1,
                'status' => WebhookDeliveryStatus::Failed,
                'response_body' => Str::limit($e->getMessage(), 2000),
            ]);

            throw $e;
        }
    }
}
