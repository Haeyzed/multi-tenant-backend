<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\CustomerGroup;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read CustomerGroup $resource
 *
 * @mixin CustomerGroup
 */
#[SchemaName('CustomerGroup')]
class CustomerGroupResource extends Resource
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
            'description' => $this->description,
            'discount_percent' => $this->discount_percent,
            'price_list_id' => $this->price_list_id,
            'is_active' => $this->is_active,
            'customers_count' => $this->when(isset($this->customers_count), $this->customers_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
