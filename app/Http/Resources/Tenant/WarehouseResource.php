<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\Warehouse;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read Warehouse $resource
 *
 * @mixin Warehouse
 */
#[SchemaName('Warehouse')]
class WarehouseResource extends Resource
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
            'address' => $this->address,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'stocks_count' => $this->when(isset($this->stocks_count), $this->stocks_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
