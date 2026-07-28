<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\WarehouseTransferStatus;
use Database\Factories\Tenant\WarehouseTransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Inter-warehouse stock transfer document.
 *
 * @property int $id
 * @property string $number
 * @property int $source_warehouse_id
 * @property int $destination_warehouse_id
 * @property WarehouseTransferStatus $status
 * @property string|null $notes
 * @property string|null $dispatch_notes
 * @property int $transfer_cost
 * @property string|null $currency
 * @property int|null $requested_by
 * @property int|null $approved_by
 * @property int|null $dispatched_by
 * @property int|null $received_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $dispatched_at
 * @property Carbon|null $received_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Warehouse $sourceWarehouse
 * @property-read Warehouse $destinationWarehouse
 * @property-read Collection<int, WarehouseTransferItem> $items
 */
#[Fillable([
    'number',
    'source_warehouse_id',
    'destination_warehouse_id',
    'status',
    'notes',
    'dispatch_notes',
    'transfer_cost',
    'currency',
    'requested_by',
    'approved_by',
    'dispatched_by',
    'received_by',
    'approved_at',
    'dispatched_at',
    'received_at',
    'cancelled_at',
])]
class WarehouseTransfer extends Model
{
    /** @use HasFactory<WarehouseTransferFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WarehouseTransferStatus::class,
            'transfer_cost' => 'integer',
            'approved_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'received_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<WarehouseTransferItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(WarehouseTransferItem::class);
    }
}
