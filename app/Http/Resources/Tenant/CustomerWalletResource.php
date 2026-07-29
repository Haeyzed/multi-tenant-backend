<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\CustomerWallet;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read CustomerWallet $resource
 *
 * @mixin CustomerWallet
 */
#[SchemaName('CustomerWallet')]
class CustomerWalletResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'balance' => $this->balance,
            'loyalty_points' => $this->loyalty_points,
            'currency' => $this->currency,
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'ledgers' => CustomerWalletLedgerResource::collection($this->whenLoaded('ledgers')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
