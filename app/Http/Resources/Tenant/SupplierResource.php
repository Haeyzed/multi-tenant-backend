<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\Supplier;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read Supplier $resource
 *
 * @mixin Supplier
 */
#[SchemaName('Supplier')]
class SupplierResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_group_id' => $this->supplier_group_id,
            'name' => $this->name,
            'code' => $this->code,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'currency' => $this->currency,
            'tax_id' => $this->tax_id,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'group' => new SupplierGroupResource($this->whenLoaded('group')),
            'products' => SupplierProductResource::collection($this->whenLoaded('products')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
