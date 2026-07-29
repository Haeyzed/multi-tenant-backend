<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use Illuminate\Http\Request;

class ProductFamilyResource extends Resource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'code' => $this->code,
            'description' => $this->description, 'is_active' => $this->is_active,
            'products_count' => $this->when(isset($this->products_count), $this->products_count),
            'attribute_sets_count' => $this->when(isset($this->attribute_sets_count), $this->attribute_sets_count),
            'created_at' => $this->created_at, 'updated_at' => $this->updated_at,
        ];
    }
}
