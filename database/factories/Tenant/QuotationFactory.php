<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\QuotationStatus;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'QUO-'.Str::upper(Str::random(10)),
            'customer_id' => Customer::factory(),
            'status' => QuotationStatus::Draft,
            'currency' => 'USD',
            'subtotal' => 0,
            'tax' => 0,
            'total' => 0,
        ];
    }
}
