<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Line item on a warehouse transfer.
 *
 * @property int $id
 * @property int $warehouse_transfer_id
 * @property int $product_id
 * @property int $quantity
 * @property int $quantity_received
 * @property int|null $source_bin_id
 * @property int|null $destination_bin_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WarehouseTransfer $transfer
 * @property-read Product $product
 * @property-read WarehouseBin|null $sourceBin
 * @property-read WarehouseBin|null $destinationBin
 */
#[Fillable([
    'warehouse_transfer_id',
    'product_id',
    'quantity',
    'quantity_received',
    'source_bin_id',
    'destination_bin_id',
])]
class WarehouseTransferItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_received' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<WarehouseTransfer, $this>
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(WarehouseTransfer::class, 'warehouse_transfer_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<WarehouseBin, $this>
     */
    public function sourceBin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class, 'source_bin_id');
    }

    /**
     * @return BelongsTo<WarehouseBin, $this>
     */
    public function destinationBin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class, 'destination_bin_id');
    }
}
