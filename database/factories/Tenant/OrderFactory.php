<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\OrderStatus;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'ORD-'.Str::upper(Str::random(10)),
            'customer_id' => Customer::factory(),
            'status' => OrderStatus::Draft,
            'currency' => strtoupper((string) config('billing.default_currency', 'USD')),
            'subtotal' => 0,
            'tax' => 0,
            'total' => 0,
            'notes' => null,
            'placed_at' => null,
            'inventory_decremented' => false,
        ];
    }
}
