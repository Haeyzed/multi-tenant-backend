<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default billing gateway
    |--------------------------------------------------------------------------
    |
    | Must also appear in enabled_gateways.
    | Supported drivers: fake, stripe, paystack, flutterwave
    |
    */
    'default_gateway' => env('BILLING_GATEWAY', 'fake'),

    /*
    |--------------------------------------------------------------------------
    | Enabled gateways
    |--------------------------------------------------------------------------
    |
    | Only these drivers may be selected for subscribe/webhook resolution.
    | Paystack and Flutterwave are implemented; enable them explicitly via
    | BILLING_ENABLED_GATEWAYS when credentials are configured.
    |
    */
    'enabled_gateways' => array_values(array_filter(array_map(
        static fn (string $value): string => trim($value),
        explode(',', (string) env('BILLING_ENABLED_GATEWAYS', 'fake,stripe')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Default display / seeding currency (ISO 4217)
    |--------------------------------------------------------------------------
    */
    'default_currency' => env('BILLING_DEFAULT_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Entitlement enforcement
    |--------------------------------------------------------------------------
    |
    | When enforce_limits is true, creating users/domains is blocked once the
    | tenant's active plan feature limit is reached. When no entitling
    | subscription exists, limits are skipped unless require_subscription is true.
    |
    */
    'enforce_limits' => env('BILLING_ENFORCE_LIMITS', true),
    'require_subscription' => env('BILLING_REQUIRE_SUBSCRIPTION', false),

    /*
    |--------------------------------------------------------------------------
    | Auto-subscribe on tenant provision
    |--------------------------------------------------------------------------
    |
    | When enabled, newly provisioned tenants are subscribed to the plan matching
    | default_plan_slug (typically Free) when that plan and an active price exist.
    | Missing catalog entries are skipped so local/test setups without PlanSeeder
    | continue to work.
    |
    */
    'auto_subscribe_on_provision' => env('BILLING_AUTO_SUBSCRIBE_ON_PROVISION', true),
    'default_plan_slug' => env('BILLING_DEFAULT_PLAN_SLUG', 'free'),

    /*
    |--------------------------------------------------------------------------
    | Past-due grace window (days)
    |--------------------------------------------------------------------------
    |
    | After a payment failure the subscription becomes past_due and keeps access
    | until ends_at (now + grace_days). When that window ends, lifecycle moves
    | the subscription to suspended (no access).
    |
    */
    'grace_days' => (int) env('BILLING_GRACE_DAYS', 3),

    /*
    |--------------------------------------------------------------------------
    | Local proration on plan changes
    |--------------------------------------------------------------------------
    |
    | When enabled, Fake (and other local) plan changes create a prorated invoice
    | for the remaining period: charge(new) - credit(old), floored at zero.
    | Stripe still uses gateway-side proration_behavior independently.
    |
    */
    'proration_enabled' => (bool) env('BILLING_PRORATION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Trial ending soon notice (days)
    |--------------------------------------------------------------------------
    */
    'trial_ending_soon_days' => (int) env('BILLING_TRIAL_ENDING_SOON_DAYS', 3),

    'gateways' => [
        'fake' => [
            'webhook_secret' => env('BILLING_FAKE_WEBHOOK_SECRET'),
        ],
        'stripe' => [
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'base_url' => env('STRIPE_API_BASE', 'https://api.stripe.com/v1/'),
        ],
        'paystack' => [
            'secret' => env('PAYSTACK_SECRET'),
            'public_key' => env('PAYSTACK_PUBLIC_KEY'),
            'base_url' => env('PAYSTACK_API_BASE', 'https://api.paystack.co/'),
        ],
        'flutterwave' => [
            'secret' => env('FLUTTERWAVE_SECRET'),
            'secret_hash' => env('FLUTTERWAVE_SECRET_HASH'),
            'base_url' => env('FLUTTERWAVE_API_BASE', 'https://api.flutterwave.com/v3/'),
        ],
    ],
];
