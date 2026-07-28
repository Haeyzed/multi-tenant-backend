<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\ProductOptionValue;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read ProductOptionValue $resource
 *
 * @mixin ProductOptionValue
 */
#[SchemaName('ProductOptionValue')]
class ProductOptionValueResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_option_id' => $this->product_option_id,
            'value' => $this->value,
            'position' => $this->position,
            'option' => new ProductOptionResource($this->whenLoaded('option')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
