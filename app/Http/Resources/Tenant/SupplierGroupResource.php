<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\SupplierGroup;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read SupplierGroup $resource
 *
 * @mixin SupplierGroup
 */
#[SchemaName('SupplierGroup')]
class SupplierGroupResource extends Resource
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
            'is_active' => $this->is_active,
            'suppliers_count' => $this->when(isset($this->suppliers_count), $this->suppliers_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
