<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\PriceListAssignment;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read PriceListAssignment $resource
 *
 * @mixin PriceListAssignment
 */
#[SchemaName('PriceListAssignment')]
class PriceListAssignmentResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'price_list_id' => $this->price_list_id,
            'assignable_type' => $this->assignable_type instanceof \BackedEnum
                ? $this->assignable_type->value
                : $this->assignable_type,
            'assignable_id' => $this->assignable_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
