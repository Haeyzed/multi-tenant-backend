<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexWebhookDeliveryRequest;
use App\Http\Requests\Tenant\IndexWebhookEndpointRequest;
use App\Http\Requests\Tenant\StoreWebhookEndpointRequest;
use App\Http\Requests\Tenant\UpdateWebhookEndpointRequest;
use App\Http\Resources\ResourceCollection;
use App\Http\Resources\Tenant\WebhookDeliveryResource;
use App\Http\Resources\Tenant\WebhookEndpointResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\WebhookEndpoint;
use App\Services\Tenant\WebhookService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as DocsResponse;
use Illuminate\Http\JsonResponse;

#[Group('Webhooks')]
class WebhookEndpointController extends Controller
{
    public function __construct(private WebhookService $webhooks) {}

    /**
     * @operationId listWebhookEndpoints
     */
    public function index(IndexWebhookEndpointRequest $request): ResourceCollection
    {
        return WebhookEndpointResource::collection($this->webhooks->list($request->perPage()))
            ->withMessage('Webhook endpoints retrieved successfully.');
    }

    /**
     * @operationId createWebhookEndpoint
     */
    #[DocsResponse(status: 201, description: 'Webhook endpoint created.', type: 'array{success: true, message: string, data: WebhookEndpointResource, meta: null, errors: null}')]
    public function store(StoreWebhookEndpointRequest $request): JsonResponse
    {
        $endpoint = $this->webhooks->create($request->webhookEndpointData());

        return ApiResponse::success(
            data: (new WebhookEndpointResource($endpoint))->resolve(),
            message: 'Webhook endpoint created successfully.',
            status: 201,
        );
    }

    /**
     * @operationId showWebhookEndpoint
     */
    #[PathParameter('webhook_endpoint', description: 'Webhook endpoint ID.', type: 'integer', example: 1)]
    public function show(WebhookEndpoint $webhookEndpoint): WebhookEndpointResource
    {
        $this->authorize('view', $webhookEndpoint);

        return (new WebhookEndpointResource($this->webhooks->find($webhookEndpoint)))
            ->withMessage('Webhook endpoint retrieved successfully.');
    }

    /**
     * @operationId updateWebhookEndpoint
     */
    #[PathParameter('webhook_endpoint', description: 'Webhook endpoint ID.', type: 'integer', example: 1)]
    public function update(UpdateWebhookEndpointRequest $request, WebhookEndpoint $webhookEndpoint): WebhookEndpointResource
    {
        return (new WebhookEndpointResource($this->webhooks->update($webhookEndpoint, $request->webhookEndpointData())))
            ->withMessage('Webhook endpoint updated successfully.');
    }

    /**
     * @operationId deleteWebhookEndpoint
     */
    #[PathParameter('webhook_endpoint', description: 'Webhook endpoint ID.', type: 'integer', example: 1)]
    public function destroy(WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        $this->authorize('delete', $webhookEndpoint);
        $this->webhooks->delete($webhookEndpoint);

        return ApiResponse::success(message: 'Webhook endpoint deleted successfully.');
    }

    /**
     * @operationId listWebhookDeliveries
     */
    #[PathParameter('webhook_endpoint', description: 'Webhook endpoint ID.', type: 'integer', example: 1)]
    public function deliveries(IndexWebhookDeliveryRequest $request, WebhookEndpoint $webhookEndpoint): ResourceCollection
    {
        return WebhookDeliveryResource::collection($this->webhooks->listDeliveries($webhookEndpoint, $request->perPage()))
            ->withMessage('Webhook deliveries retrieved successfully.');
    }
}
