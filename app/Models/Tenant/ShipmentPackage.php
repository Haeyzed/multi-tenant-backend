<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $shipment_id
 * @property string|null $label
 * @property string|null $label_provider
 * @property string|null $label_url
 * @property array<string, mixed>|null $label_payload
 * @property int|null $weight_grams
 * @property string|null $dimensions
 * @property string|null $tracking_number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'shipment_id',
    'label',
    'label_provider',
    'label_url',
    'label_payload',
    'weight_grams',
    'dimensions',
    'tracking_number',
])]
class ShipmentPackage extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'label_payload' => 'array',
            'weight_grams' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Shipment, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
