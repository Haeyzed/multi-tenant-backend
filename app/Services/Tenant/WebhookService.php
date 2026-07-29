<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\WebhookDeliveryStatus;
use App\Jobs\Tenant\DeliverWebhookJob;
use App\Models\Tenant\WebhookDelivery;
use App\Models\Tenant\WebhookEndpoint;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Outbound webhook endpoint management and event fan-out.
 */
final class WebhookService
{
    /**
     * @return LengthAwarePaginator<int, WebhookEndpoint>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(WebhookEndpoint::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::partial('name'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('name'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{name: string, url: string, secret?: string|null, events: list<string>, is_active?: bool}  $data
     */
    public function create(array $data): WebhookEndpoint
    {
        return WebhookEndpoint::query()->create([
            'name' => $data['name'],
            'url' => $data['url'],
            'secret' => $data['secret'] ?? Str::random(32),
            'events' => array_values($data['events']),
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Load the webhook endpoint with its deliveries count.
     */
    public function find(WebhookEndpoint $endpoint): WebhookEndpoint
    {
        return $endpoint->loadCount('deliveries');
    }

    /**
     * @param  array{name?: string, url?: string, secret?: string|null, events?: list<string>, is_active?: bool}  $data
     */
    public function update(WebhookEndpoint $endpoint, array $data): WebhookEndpoint
    {
        if (isset($data['events'])) {
            $data['events'] = array_values($data['events']);
        }

        $endpoint->fill($data)->save();

        return $endpoint->refresh();
    }

    /**
     * Delete a webhook endpoint.
     */
    public function delete(WebhookEndpoint $endpoint): void
    {
        $endpoint->delete();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(string $event, array $payload): void
    {
        $endpoints = WebhookEndpoint::query()
            ->where('is_active', true)
            ->get()
            ->filter(function (WebhookEndpoint $endpoint) use ($event): bool {
                $events = $endpoint->events ?? [];

                return in_array('*', $events, true) || in_array($event, $events, true);
            });

        foreach ($endpoints as $endpoint) {
            /** @var WebhookDelivery $delivery */
            $delivery = WebhookDelivery::query()->create([
                'webhook_endpoint_id' => $endpoint->id,
                'event' => $event,
                'payload' => $payload,
                'status' => WebhookDeliveryStatus::Pending,
                'attempts' => 0,
            ]);

            DeliverWebhookJob::dispatch($delivery->id);
        }
    }

    /**
     * @return LengthAwarePaginator<int, WebhookDelivery>
     */
    public function listDeliveries(WebhookEndpoint $endpoint, int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for($endpoint->deliveries()->getQuery())
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('event'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }
}
