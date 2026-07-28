<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Billing\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(500, 20000);

        return [
            'tenant_id' => Tenant::factory()->withDomain(),
            'subscription_id' => null,
            'coupon_id' => null,
            'number' => 'INV-'.Str::upper(Str::random(10)),
            'currency' => strtoupper((string) config('billing.default_currency', 'USD')),
            'subtotal' => $amount,
            'tax' => 0,
            'total' => $amount,
            'status' => InvoiceStatus::Paid,
            'due_at' => now(),
            'paid_at' => now(),
        ];
    }
}
