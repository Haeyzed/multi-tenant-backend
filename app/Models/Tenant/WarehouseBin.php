<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Bin / shelf location within a warehouse zone.
 *
 * @property int $id
 * @property int $warehouse_id
 * @property int|null $warehouse_zone_id
 * @property string $name
 * @property string $code
 * @property string|null $aisle
 * @property string|null $rack
 * @property string|null $shelf
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'warehouse_id',
    'warehouse_zone_id',
    'name',
    'code',
    'aisle',
    'rack',
    'shelf',
    'is_active',
])]
class WarehouseBin extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<WarehouseZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(WarehouseZone::class, 'warehouse_zone_id');
    }
}
