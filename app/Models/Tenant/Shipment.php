<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\ShipmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Outbound shipment for an order / fulfilment.
 *
 * @property int $id
 * @property string $number
 * @property int $order_id
 * @property int|null $fulfilment_id
 * @property ShipmentStatus $status
 * @property string|null $carrier
 * @property int|null $shipping_carrier_id
 * @property int|null $shipping_method_id
 * @property string|null $tracking_number
 * @property string|null $notes
 * @property Carbon|null $shipped_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'number',
    'order_id',
    'fulfilment_id',
    'status',
    'carrier',
    'shipping_carrier_id',
    'shipping_method_id',
    'tracking_number',
    'notes',
    'shipped_at',
    'delivered_at',
])]
class Shipment extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Fulfilment, $this>
     */
    public function fulfilment(): BelongsTo
    {
        return $this->belongsTo(Fulfilment::class);
    }

    /**
     * @return BelongsTo<ShippingCarrier, $this>
     */
    public function shippingCarrier(): BelongsTo
    {
        return $this->belongsTo(ShippingCarrier::class);
    }

    /**
     * @return BelongsTo<ShippingMethod, $this>
     */
    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    /**
     * @return HasMany<ShipmentPackage, $this>
     */
    public function packages(): HasMany
    {
        return $this->hasMany(ShipmentPackage::class);
    }
}
