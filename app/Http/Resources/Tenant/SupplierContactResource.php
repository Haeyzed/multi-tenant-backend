<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\SupplierContact;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read SupplierContact $resource
 *
 * @mixin SupplierContact
 */
#[SchemaName('SupplierContact')]
class SupplierContactResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'title' => $this->title,
            'is_primary' => $this->is_primary,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
