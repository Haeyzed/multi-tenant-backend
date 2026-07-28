<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\Customer;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read Customer $resource
 *
 * @mixin Customer
 */
#[SchemaName('Customer')]
class CustomerResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'customer_group_id' => $this->customer_group_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'credit_limit' => $this->credit_limit,
            'currency' => $this->currency,
            'tax_exempt' => $this->tax_exempt,
            'tax_id' => $this->tax_id,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'orders_count' => $this->when(isset($this->orders_count), $this->orders_count),
            'addresses_count' => $this->when(isset($this->addresses_count), $this->addresses_count),
            'contacts_count' => $this->when(isset($this->contacts_count), $this->contacts_count),
            'crm_notes_count' => $this->when(isset($this->crm_notes_count), $this->crm_notes_count),
            'group' => CustomerGroupResource::make($this->whenLoaded('group')),
            'tags' => CustomerTagResource::collection($this->whenLoaded('tags')),
            'addresses' => CustomerAddressResource::collection($this->whenLoaded('addresses')),
            'contacts' => CustomerContactResource::collection($this->whenLoaded('contacts')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
