<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\SalesInvoiceStatus;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\SalesInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SalesInvoice>
 */
class SalesInvoiceFactory extends Factory
{
    protected $model = SalesInvoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'SINV-'.Str::upper(Str::random(10)),
            'order_id' => Order::factory(),
            'customer_id' => Customer::factory(),
            'status' => SalesInvoiceStatus::Issued,
            'currency' => strtoupper((string) config('billing.default_currency', 'USD')),
            'subtotal' => 1000,
            'tax' => 0,
            'total' => 1000,
            'notes' => null,
            'issued_at' => now(),
            'paid_at' => null,
        ];
    }
}
