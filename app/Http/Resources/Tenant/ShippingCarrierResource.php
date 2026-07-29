<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\ShippingCarrier;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read ShippingCarrier $resource
 *
 * @mixin ShippingCarrier
 */
#[SchemaName('ShippingCarrier')]
class ShippingCarrierResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'tracking_url_template' => $this->tracking_url_template,
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'methods_count' => $this->when(isset($this->methods_count), $this->methods_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
