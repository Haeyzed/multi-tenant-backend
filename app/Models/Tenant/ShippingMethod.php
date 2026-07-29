<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Carrier + zone shipping method with a flat rate.
 *
 * @property int $id
 * @property int $shipping_carrier_id
 * @property int|null $shipping_zone_id
 * @property string $name
 * @property string $code
 * @property int $rate
 * @property string $currency
 * @property int|null $min_order_total
 * @property int|null $max_order_total
 * @property int|null $estimated_days_min
 * @property int|null $estimated_days_max
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'shipping_carrier_id',
    'shipping_zone_id',
    'name',
    'code',
    'rate',
    'currency',
    'min_order_total',
    'max_order_total',
    'estimated_days_min',
    'estimated_days_max',
    'is_active',
])]
class ShippingMethod extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'integer',
            'min_order_total' => 'integer',
            'max_order_total' => 'integer',
            'estimated_days_min' => 'integer',
            'estimated_days_max' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ShippingCarrier, $this>
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(ShippingCarrier::class, 'shipping_carrier_id');
    }

    /**
     * @return BelongsTo<ShippingZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    /**
     * @return HasMany<Shipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
