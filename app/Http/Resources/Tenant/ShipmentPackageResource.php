<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\ShipmentPackage;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read ShipmentPackage $resource
 *
 * @mixin ShipmentPackage
 */
#[SchemaName('ShipmentPackage')]
class ShipmentPackageResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'weight_grams' => $this->weight_grams,
            'dimensions' => $this->dimensions,
            'tracking_number' => $this->tracking_number,
        ];
    }
}
