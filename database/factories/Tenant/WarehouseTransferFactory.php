<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\WarehouseTransferStatus;
use App\Models\Tenant\Warehouse;
use App\Models\Tenant\WarehouseTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WarehouseTransfer>
 */
class WarehouseTransferFactory extends Factory
{
    protected $model = WarehouseTransfer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'TRF-'.Str::upper(Str::random(10)),
            'source_warehouse_id' => Warehouse::factory(),
            'destination_warehouse_id' => Warehouse::factory(),
            'status' => WarehouseTransferStatus::Draft,
            'notes' => null,
            'transfer_cost' => 0,
            'currency' => null,
        ];
    }
}
