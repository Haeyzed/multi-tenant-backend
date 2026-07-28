<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Enums\Central\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Central\WebhookEventResource;
use App\Http\Resources\ResourceCollection;
use App\Models\WebhookEvent;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

#[Group('Billing Webhooks')]
class WebhookEventController extends Controller
{
    /**
     * @operationId listBillingWebhookEvents
     */
    public function index(Request $request): ResourceCollection
    {
        abort_unless(request()->user()?->can(Permission::WebhooksView->value) ?? false, 403);

        $perPage = min(100, max(1, (int) $request->integer('per_page', 25)));

        $events = QueryBuilder::for(WebhookEvent::class)
            ->allowedFilters(
                AllowedFilter::exact('gateway'),
                AllowedFilter::exact('status'),
                AllowedFilter::partial('type'),
            )
            ->allowedSorts(
                AllowedSort::field('created_at'),
                AllowedSort::field('processed_at'),
                AllowedSort::field('id'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());

        return WebhookEventResource::collection($events)
            ->withMessage('Webhook events retrieved successfully.');
    }

    /**
     * @operationId showBillingWebhookEvent
     */
    #[PathParameter('webhookEvent', description: 'Webhook event ID.', type: 'integer', example: 1)]
    public function show(WebhookEvent $webhookEvent): WebhookEventResource
    {
        abort_unless(request()->user()?->can(Permission::WebhooksView->value) ?? false, 403);

        return (new WebhookEventResource($webhookEvent))
            ->withMessage('Webhook event retrieved successfully.');
    }
}
