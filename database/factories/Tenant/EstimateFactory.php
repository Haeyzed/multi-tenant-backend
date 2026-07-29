<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\EstimateStatus;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Estimate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Estimate>
 */
class EstimateFactory extends Factory
{
    protected $model = Estimate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'EST-'.Str::upper(Str::random(8)),
            'customer_id' => Customer::factory(),
            'tax_id' => null,
            'status' => EstimateStatus::Draft,
            'currency' => 'USD',
            'subtotal' => 1000,
            'tax' => 0,
            'total' => 1000,
            'notes' => null,
            'valid_until' => null,
        ];
    }
}
