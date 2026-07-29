<?php

declare(strict_types=1);

namespace App\Contracts\Billing;

use App\DataTransferObjects\Billing\GatewaySubscriptionResult;
use App\Enums\Billing\BillingGateway;
use App\Models\Central\PlanPrice;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use Illuminate\Http\Request;

/**
 * Payment provider driver for tenant subscriptions.
 */
interface PaymentGateway
{
    public function name(): BillingGateway;

    /**
     * @param  array<string, mixed>  $options
     */
    public function createSubscription(
        Tenant $tenant,
        PlanPrice $price,
        ?string $customerId = null,
        array $options = [],
    ): GatewaySubscriptionResult;

    public function cancelSubscription(Subscription $subscription, bool $atPeriodEnd = true): GatewaySubscriptionResult;

    public function resumeSubscription(Subscription $subscription): GatewaySubscriptionResult;

    public function changePlan(Subscription $subscription, PlanPrice $newPrice): GatewaySubscriptionResult;

    public function verifyWebhookSignature(Request $request): bool;

    /**
     * @return array{id: string, type: string, payload: array<string, mixed>}
     */
    public function parseWebhook(Request $request): array;
}
