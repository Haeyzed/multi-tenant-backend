<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Billing\BillingGateway;
use App\Enums\Billing\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'tenant_id' => fn (array $attributes) => Invoice::query()->find($attributes['invoice_id'])?->tenant_id,
            'amount' => fake()->numberBetween(500, 20000),
            'currency' => strtoupper((string) config('billing.default_currency', 'USD')),
            'status' => PaymentStatus::Succeeded,
            'gateway' => BillingGateway::Fake,
            'gateway_payment_id' => 'pay_fake_'.fake()->bothify('??????????'),
            'paid_at' => now(),
        ];
    }
}
