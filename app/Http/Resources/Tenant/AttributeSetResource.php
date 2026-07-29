<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use Illuminate\Http\Request;

class AttributeSetResource extends Resource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'code' => $this->code,
            'product_family_id' => $this->product_family_id, 'description' => $this->description,
            'is_active' => $this->is_active,
            'attributes' => AttributeResource::collection($this->whenLoaded('attributes')),
            'created_at' => $this->created_at, 'updated_at' => $this->updated_at,
        ];
    }
}
