<?php

declare(strict_types=1);

namespace App\Enums\Billing;

enum BillingGateway: string
{
    case Fake = 'fake';
    case Stripe = 'stripe';
    case Paystack = 'paystack';
    case Flutterwave = 'flutterwave';
}
