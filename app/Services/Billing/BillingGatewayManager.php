<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Billing\PaymentGateway;
use App\Enums\Billing\BillingGateway;
use App\Services\Billing\Drivers\FakePaymentGateway;
use App\Services\Billing\Drivers\FlutterwavePaymentGateway;
use App\Services\Billing\Drivers\PaystackPaymentGateway;
use App\Services\Billing\Drivers\StripePaymentGateway;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Resolves the configured payment gateway driver.
 */
final class BillingGatewayManager
{
    /**
     * Resolve the driver for the given gateway, defaulting to the configured gateway.
     *
     * @throws ValidationException if the resolved gateway is not enabled
     */
    public function driver(?BillingGateway $gateway = null): PaymentGateway
    {
        $gateway ??= BillingGateway::tryFrom((string) config('billing.default_gateway', 'fake'))
            ?? BillingGateway::Fake;

        $this->assertEnabled($gateway);

        return match ($gateway) {
            BillingGateway::Fake => app(FakePaymentGateway::class),
            BillingGateway::Stripe => app(StripePaymentGateway::class),
            BillingGateway::Paystack => app(PaystackPaymentGateway::class),
            BillingGateway::Flutterwave => app(FlutterwavePaymentGateway::class),
            default => throw new InvalidArgumentException("Unsupported billing gateway [{$gateway->value}]."),
        };
    }

    /**
     * @return list<string>
     */
    public function enabledGateways(): array
    {
        /** @var list<string>|string $configured */
        $configured = config('billing.enabled_gateways', ['fake']);

        if (is_string($configured)) {
            $configured = array_map(trim(...), explode(',', $configured));
        }

        return array_values(array_filter($configured, static fn (string $value): bool => $value !== ''));
    }

    /**
     * @throws ValidationException if the gateway is not in the enabled gateways list
     */
    public function assertEnabled(BillingGateway $gateway): void
    {
        if (! in_array($gateway->value, $this->enabledGateways(), true)) {
            throw ValidationException::withMessages([
                'gateway' => ["The [{$gateway->value}] billing gateway is not enabled."],
            ]);
        }
    }
}
