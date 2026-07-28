<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Enums\Billing\BillingGateway;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Central\WebhookService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Billing Webhooks')]
class WebhookController extends Controller
{
    public function __construct(private WebhookService $webhooks) {}

    /**
     * Receive a billing gateway webhook.
     *
     * @operationId receiveBillingWebhook
     */
    #[PathParameter('gateway', description: 'Gateway slug (fake, stripe, paystack, flutterwave).', type: 'string', example: 'fake')]
    public function __invoke(Request $request, string $gateway): JsonResponse
    {
        $gatewayEnum = BillingGateway::tryFrom($gateway);

        abort_if($gatewayEnum === null, 404, 'Unknown billing gateway.');

        $event = $this->webhooks->handle($gatewayEnum, $request);

        return ApiResponse::success(
            data: [
                'id' => $event->id,
                'event_id' => $event->event_id,
                'status' => $event->status->value,
            ],
            message: 'Webhook accepted.',
        );
    }
}
